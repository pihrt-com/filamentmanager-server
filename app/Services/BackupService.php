<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use RuntimeException;
use ZipArchive;

final class BackupService
{
    private const CORE_TABLES = ['workspaces','users','devices','manufacturers','materials','locations','spools','printers','printer_slots','spool_movements','settings','sync_changes','audit_log'];
    private const OPTIONAL_TABLES = ['user_notification_settings','notification_states','mail_queue','print_jobs','print_job_consumptions'];
    private const TABLES = [...self::CORE_TABLES,...self::OPTIONAL_TABLES];
    public function __construct(private readonly App $app) {}

    public function create(string $reason = 'manual'): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $timestamp = gmdate('Ymd-His');
        $filename = "filamentmanager-backup-{$timestamp}.zip";
        $path = FM_ROOT . '/storage/backups/' . $filename;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create backup archive.');
        $manifest = ['format'=>'filamentmanager-backup','formatVersion'=>1,'serverVersion'=>trim((string)file_get_contents(FM_ROOT.'/VERSION')),'createdAt'=>gmdate('c'),'reason'=>$reason,'tables'=>self::TABLES];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        foreach (self::TABLES as $table) {
            $rows = $this->app->db()->fetchAll('SELECT * FROM `' . $table . '`');
            if($table==='settings')$rows=array_values(array_filter($rows,static fn(array $row):bool=>$row['setting_key']!=='smtp_password_encrypted'));
            if($table==='print_jobs')foreach($rows as &$row)$row['integration_token_id']=null;unset($row);
            $zip->addFromString('database/' . $table . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        }
        $zip->close();
        @chmod($path, 0640);
        $this->prunePattern('filamentmanager-backup-*.zip',(int)$this->app->config('backup_retention',10));
        return $path;
    }

    public function restore(string $path): void
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Cannot open backup archive.');
        $manifestStat=$zip->statName('manifest.json');if(!is_array($manifestStat)||(int)($manifestStat['size']??0)>1024*1024){$zip->close();throw new RuntimeException('Backup manifest is missing or excessively large.');}
        $rawManifest = $zip->getFromName('manifest.json');
        $manifest = is_string($rawManifest) ? json_decode($rawManifest,true) : null;
        if (!is_array($manifest) || ($manifest['format']??'')!=='filamentmanager-backup' || (int)($manifest['formatVersion']??0)!==1) { $zip->close(); throw new RuntimeException('Unsupported or damaged backup.'); }
        $data=[];$totalUncompressed=0;$maxEntryBytes=128*1024*1024;$maxTotalBytes=512*1024*1024;
        foreach(self::TABLES as $table){$entry='database/'.$table.'.json';$stat=$zip->statName($entry);if(!is_array($stat)&&in_array($table,self::OPTIONAL_TABLES,true)){$data[$table]=[];continue;}$size=is_array($stat)?(int)($stat['size']??0):0;if($size<0||$size>$maxEntryBytes||($totalUncompressed+=$size)>$maxTotalBytes){$zip->close();throw new RuntimeException('Backup contains excessively large data.');}$raw=$zip->getFromName($entry);if(!is_string($raw)){ $zip->close();throw new RuntimeException('Backup is missing table '.$table);}$rows=json_decode($raw,true);if(!is_array($rows)){ $zip->close();throw new RuntimeException('Invalid data for '.$table);}$data[$table]=$rows;}
        $zip->close();
        $pdo=$this->app->db()->pdo();
        $allowedColumns=[];foreach(self::TABLES as $table){$columns=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(\PDO::FETCH_COLUMN);$allowedColumns[$table]=array_fill_keys($columns,true);}
        $pdo->beginTransaction();
        try{
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach(array_reverse(self::TABLES) as $table)$pdo->exec('DELETE FROM `'.$table.'`');
            foreach(self::TABLES as $table)foreach($data[$table] as $row){if(!$row)continue;if(!is_array($row))throw new RuntimeException('Invalid backup row for '.$table);$columns=array_keys($row);foreach($columns as $column)if(!is_string($column)||!isset($allowedColumns[$table][$column]))throw new RuntimeException('Backup contains an invalid column for '.$table);$sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';$stmt=$pdo->prepare($sql);$stmt->execute(array_values($row));}
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        }catch(\Throwable $e){try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(\Throwable){}if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function createApplicationFiles(string $reason = 'pre-update'): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP extension is required.');
        $path = FM_ROOT . '/storage/backups/filamentmanager-files-' . gmdate('Ymd-His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Cannot create application backup.');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FM_ROOT, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(FM_ROOT) + 1));
            if ($relative === 'config/local.php' || str_starts_with($relative, 'storage/') || str_starts_with($relative, '.git/') || str_starts_with($relative, 'dist/')) continue;
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();
        @chmod($path, 0640);
        $this->prunePattern('filamentmanager-files-*.zip',(int)$this->app->config('backup_retention',10));
        return $path;
    }

    public function list(): array
    {
        $files=array_merge(glob(FM_ROOT.'/storage/backups/filamentmanager-backup-*.zip')?:[],glob(FM_ROOT.'/storage/backups/filamentmanager-files-*.zip')?:[]);usort($files,static fn(string $a,string $b):int=>filemtime($b)<=>filemtime($a));return array_map(static fn($p)=>['name'=>basename($p),'size'=>filesize($p),'createdAt'=>gmdate('c',filemtime($p)),'type'=>str_starts_with(basename($p),'filamentmanager-files-')?'files':'data'],$files);
    }

    public function delete(string $name): void
    {
        if(!preg_match('/^filamentmanager-(?:backup|files)-\d{8}-\d{6}\.zip$/',$name)||basename($name)!==$name)throw new RuntimeException('Invalid backup filename.');
        $path=FM_ROOT.'/storage/backups/'.$name;if(!is_file($path))throw new RuntimeException('Backup file was not found.');
        if(!unlink($path))throw new RuntimeException('Backup file could not be deleted.');clearstatcache(true,$path);if(file_exists($path))throw new RuntimeException('Backup file still exists after deletion. Check directory ownership and permissions.');
    }

    private function prunePattern(string $pattern,int $keep): void
    {
        $files=glob(FM_ROOT.'/storage/backups/'.$pattern)?:[];rsort($files,SORT_STRING);foreach(array_slice($files,max(1,$keep)) as $file)@unlink($file);
    }
}
