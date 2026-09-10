<?php

declare(strict_types=1);

define('FM_ROOT', dirname(__DIR__));
spl_autoload_register(static function(string $class):void {
    $prefix='FilamentManager\\';
    if(str_starts_with($class,$prefix)) require FM_ROOT.'/app/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
});

// Exercise real SyncService transactions against an isolated SQLite database.
// Only the MySQL locking suffix is removed; concurrency needs a MySQL run.
final class SyncTestPDO extends PDO {
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);
    }
}
$pdo=new SyncTestPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE workspaces(id TEXT PRIMARY KEY);
CREATE TABLE manufacturers(id TEXT PRIMARY KEY,workspace_id TEXT,name TEXT COLLATE NOCASE,website TEXT,notes TEXT,version INTEGER DEFAULT 1,deleted_at TEXT,UNIQUE(workspace_id,name));
CREATE TABLE manufacturer_aliases(workspace_id TEXT,alias_id TEXT,manufacturer_id TEXT,PRIMARY KEY(workspace_id,alias_id));
CREATE TABLE materials(id TEXT PRIMARY KEY,workspace_id TEXT,manufacturer_id TEXT,material_type TEXT,color_name TEXT,version INTEGER DEFAULT 1);
CREATE TABLE sync_mutations(workspace_id TEXT,device_id TEXT,client_mutation_id TEXT,result_data TEXT,UNIQUE(workspace_id,device_id,client_mutation_id));
CREATE TABLE sync_changes(sequence INTEGER PRIMARY KEY AUTOINCREMENT,workspace_id TEXT,entity_type TEXT,entity_id TEXT,operation TEXT,entity_version INTEGER,user_id TEXT,device_id TEXT);');
$db=new FilamentManager\Core\Database($pdo);
$app=new FilamentManager\Core\App([]);
(new ReflectionProperty($app,'database'))->setValue($app,$db);
$service=new FilamentManager\Services\SyncService($app);
function uid():string{return FilamentManager\Core\Uuid::v4();}
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$workspace=uid();$canonical=uid();$legacy=uid();$material=uid();
$db->execute('INSERT INTO workspaces VALUES(?)',[$workspace]);
$db->execute('INSERT INTO manufacturers(id,workspace_id,name,notes,version) VALUES(?,?,?,?,?)',[$canonical,$workspace,'ColorFil','Keep me',7]);
$user=['workspace_id'=>$workspace,'device_id'=>uid(),'id'=>uid(),'role'=>'admin'];
$create=['clientMutationId'=>uid(),'type'=>'manufacturer','id'=>$legacy,'operation'=>'upsert','baseVersion'=>0,'data'=>['name'=>'colorfil']];
$mat=['clientMutationId'=>uid(),'type'=>'material','id'=>$material,'operation'=>'upsert','baseVersion'=>0,'data'=>['manufacturer_id'=>$legacy,'material_type'=>'PLA','color_name'=>'Black']];
$response=$service->push($user,[$create,$mat]);
check(count($response['results'])===2 && !$response['conflicts'],'Legacy batch must succeed');
check($response['results'][0]['id']===$canonical,'Return canonical UUID');
check($db->fetch('SELECT manufacturer_id FROM materials WHERE id=?',[$material])['manufacturer_id']===$canonical,'Repair material reference');
check($db->fetch('SELECT COUNT(*) AS n FROM manufacturers')['n']===1,'No duplicate manufacturer');
$row=$db->fetch('SELECT * FROM manufacturers WHERE id=?',[$canonical]);
check($row['version']===7 && $row['notes']==='Keep me','Preserve canonical metadata and version');
check($service->push($user,[$create,$mat])['results']===$response['results'],'Retry must be idempotent');
$mat['clientMutationId']=uid();$mat['id']=uid();
check(count($service->push($user,[$mat])['results'])===1,'Alias must survive batch boundaries');
$db->execute('DELETE FROM sync_mutations');
$create['clientMutationId']=uid();
check($service->push($user,[$create])['results'][0]['id']===$canonical,'Alias must survive receipt cleanup');
$other=uid();$db->execute('INSERT INTO workspaces VALUES(?)',[$other]);
$otherUser=array_replace($user,['workspace_id'=>$other]);
$create['clientMutationId']=uid();
check($service->push($otherUser,[$create])['results'][0]['id']===$legacy,'Never reuse another workspace manufacturer');
$fresh=['clientMutationId'=>uid(),'type'=>'manufacturer','id'=>uid(),'operation'=>'upsert','baseVersion'=>0,'data'=>['name'=>'New Brand']];
$duplicate=array_replace($fresh,['clientMutationId'=>uid(),'id'=>uid()]);
$newResults=$service->push($user,[$fresh,$duplicate])['results'];
check(count($newResults)===2 && $newResults[0]['id']===$newResults[1]['id'],'Two new equal names must converge');
$existingEdit=array_replace($fresh,['clientMutationId'=>uid(),'baseVersion'=>1,'data'=>['name'=>'New Brand','notes'=>'Edited']]);
check($service->push($user,[$existingEdit])['results'][0]['version']===2,'Existing UUID retains normal versioned updates');
$existingEdit['clientMutationId']=uid();
check(count($service->push($user,[$existingEdit])['conflicts'])===1,'Stale edits must still conflict');
$db->execute('UPDATE manufacturers SET deleted_at=? WHERE id=?',['2026-09-10',$canonical]);
$create['clientMutationId']=uid();$create['id']=uid();
try{$service->push($user,[$create]);throw new RuntimeException('Deleted name should be rejected');}
catch(FilamentManager\Core\HttpException $e){check($e->status()===409,'Deleted name must return 409');}
echo "Manufacturer sync regression tests passed.\n";
