<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Csrf;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Request;
use FilamentManager\Core\Response;
use FilamentManager\Core\Session;
use FilamentManager\Core\Uuid;
use FilamentManager\Core\View;
use FilamentManager\Services\AuditService;
use FilamentManager\Services\ChangeService;

final class LocationController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $request, ?string $id = null): void
    {
        $user=$this->app->auth()->requireUser();
        $canManage=in_array($user['role'],['admin','manager'],true);
        if($id&&!$canManage)throw new HttpException('Permission denied',403);
        $editing=$id?$this->app->db()->fetch('SELECT * FROM locations WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$user['workspace_id']]):null;
        if($id&&!$editing)throw new HttpException('Location not found',404);
        $locations=$this->app->db()->fetchAll("SELECT l.*,p.name parent_name,(SELECT COUNT(*) FROM spools s WHERE s.location_id=l.id AND s.deleted_at IS NULL) spool_count,(SELECT COUNT(*) FROM spools s WHERE s.location_id=l.id AND s.deleted_at IS NULL AND s.status IN ('in_stock','empty') AND NOT EXISTS (SELECT 1 FROM printer_slots ps WHERE ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL)) stored_count FROM locations l LEFT JOIN locations p ON p.id=l.parent_id WHERE l.workspace_id=? AND l.deleted_at IS NULL ORDER BY COALESCE(p.name,''),l.name",[$user['workspace_id']]);

        $allAvailable=$this->app->db()->fetchAll("SELECT s.id,s.material_id,s.location_id,s.status,s.current_net_weight_g,m.material_type,m.commercial_name,m.color_name,m.color_hex,mf.id manufacturer_id,mf.name manufacturer_name,l.name location_name,l.code location_code FROM spools s JOIN materials m ON m.id=s.material_id LEFT JOIN manufacturers mf ON mf.id=m.manufacturer_id LEFT JOIN locations l ON l.id=s.location_id LEFT JOIN printer_slots ps ON ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL WHERE s.workspace_id=? AND s.deleted_at IS NULL AND s.status='in_stock' AND ps.id IS NULL ORDER BY m.material_type,m.color_name,l.name",[$user['workspace_id']]);
        $selected=['manufacturer'=>(string)$request->query('manufacturer'),'material_type'=>(string)$request->query('material_type'),'location'=>(string)$request->query('location'),'color'=>(string)$request->query('color'),'min_count'=>max(1,min(9999,(int)$request->query('min_count',1)))];
        $filtered=array_values(array_filter($allAvailable,static fn(array $spool):bool=>($selected['manufacturer']===''||($spool['manufacturer_id']??'')===$selected['manufacturer'])&&($selected['material_type']===''||$spool['material_type']===$selected['material_type'])&&($selected['location']===''||($selected['location']==='__none__'?empty($spool['location_id']):($spool['location_id']??'')===$selected['location']))&&($selected['color']===''||$spool['color_name']===$selected['color'])));
        $groups=array_values(array_filter($this->groupSpools($filtered,true),static fn(array $group):bool=>$group['spool_count']>=$selected['min_count']));
        $filters=['manufacturers'=>$this->options($allAvailable,'manufacturer_id','manufacturer_name'),'types'=>$this->values($allAvailable,'material_type'),'locations'=>$this->options($allAvailable,'location_id','location_name'),'colors'=>$this->values($allAvailable,'color_name'),'selected'=>$selected];

        View::render('locations',['title'=>View::t('storage_locations'),'locations'=>$locations,'editing'=>$editing,'canManage'=>$canManage,'inventoryGroups'=>$groups,'inventoryFilters'=>$filters,'basePath'=>$request->basePath()]);
    }

    public function detail(Request $request, string $id): void
    {
        $user=$this->app->auth()->requireUser();
        $location=$this->app->db()->fetch('SELECT l.*,p.name parent_name FROM locations l LEFT JOIN locations p ON p.id=l.parent_id WHERE l.id=? AND l.workspace_id=? AND l.deleted_at IS NULL',[$id,$user['workspace_id']]);
        if(!$location)throw new HttpException('Location not found',404);
        $spools=$this->app->db()->fetchAll('SELECT s.*,m.material_type,m.commercial_name,m.color_name,m.color_hex,mf.name manufacturer_name,ps.slot_number,p.name printer_name FROM spools s JOIN materials m ON m.id=s.material_id LEFT JOIN manufacturers mf ON mf.id=m.manufacturer_id LEFT JOIN printer_slots ps ON ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL LEFT JOIN printers p ON p.id=ps.printer_id AND p.deleted_at IS NULL WHERE s.workspace_id=? AND s.location_id=? AND s.deleted_at IS NULL ORDER BY m.material_type,m.color_name,s.status,s.id',[$user['workspace_id'],$id]);
        foreach($spools as &$spool)$spool['display_status']=$spool['printer_name']?'loaded':$spool['status'];
        unset($spool);
        $occupied=count(array_filter($spools,static fn(array $s):bool=>in_array($s['display_status'],['in_stock','empty'],true)));
        $capacity=$location['spool_capacity']!==null?(int)$location['spool_capacity']:null;
        $summary=['total'=>count($spools),'available'=>count(array_filter($spools,static fn(array $s):bool=>$s['display_status']==='in_stock')),'loaded'=>count(array_filter($spools,static fn(array $s):bool=>$s['display_status']==='loaded')),'empty'=>count(array_filter($spools,static fn(array $s):bool=>$s['display_status']==='empty')),'capacity'=>$capacity,'occupied'=>$occupied,'free'=>$capacity===null?null:max(0,$capacity-$occupied),'full'=>$capacity!==null&&$occupied>=$capacity];
        View::render('location_detail',['title'=>View::t('location_detail'),'location'=>$location,'spools'=>$spools,'materialGroups'=>$this->groupSpools($spools,false),'summary'=>$summary,'canEditSpools'=>$user['role']!=='viewer','basePath'=>$request->basePath()]);
    }

    public function save(Request $request, ?string $id = null): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager');$name=trim((string)$request->input('name'));if($name===''||mb_strlen($name)>120)throw new HttpException('Invalid location',422);$parent=trim((string)$request->input('parent_id'))?:null;if($id!==null&&$parent===$id){Session::flash('error',View::t('location_parent_self'));Response::redirect($request->basePath().'/locations/'.$id.'/edit');}if($parent&&!$this->app->db()->fetch('SELECT id FROM locations WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$parent,$user['workspace_id']]))throw new HttpException('Invalid parent location',422);$capacityRaw=trim((string)$request->input('spool_capacity'));$capacity=$capacityRaw===''?null:(int)$capacityRaw;if($capacity!==null&&($capacity<1||$capacity>999999))throw new HttpException('Invalid spool capacity',422);$before=null;
        if($id){$before=$this->app->db()->fetch('SELECT * FROM locations WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$user['workspace_id']]);if(!$before)throw new HttpException('Location not found',404);$version=(int)$before['version']+1;$this->app->db()->execute('UPDATE locations SET parent_id=?,name=?,code=?,description=?,spool_capacity=?,version=? WHERE id=?',[$parent,$name,trim((string)$request->input('code'))?:null,trim((string)$request->input('description'))?:null,$capacity,$version,$id]);}else{$id=Uuid::v4();$version=1;$this->app->db()->execute('INSERT INTO locations(id,workspace_id,parent_id,name,code,description,spool_capacity) VALUES(?,?,?,?,?,?,?)',[$id,$user['workspace_id'],$parent,$name,trim((string)$request->input('code'))?:null,trim((string)$request->input('description'))?:null,$capacity]);}
        (new ChangeService($this->app))->record($user['workspace_id'],'location',$id,'upsert',$version,$user['id']);$after=$this->app->db()->fetch('SELECT * FROM locations WHERE id=?',[$id]);(new AuditService($this->app))->record($before?'location.updated':'location.created','location',$id,$before,$after);Session::flash('success',View::t('saved'));Response::redirect($request->basePath().'/locations');
    }

    public function delete(Request $request, string $id): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager');$location=$this->app->db()->fetch('SELECT * FROM locations WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$user['workspace_id']]);if(!$location){Session::flash('error',View::t('location_not_found'));Response::redirect($request->basePath().'/locations');}if($this->app->db()->fetch('SELECT id FROM spools WHERE location_id=? AND deleted_at IS NULL LIMIT 1',[$id])||$this->app->db()->fetch('SELECT id FROM locations WHERE parent_id=? AND deleted_at IS NULL LIMIT 1',[$id])){Session::flash('error',View::t('location_in_use'));Response::redirect($request->basePath().'/locations');}$version=(int)$location['version']+1;$this->app->db()->execute('UPDATE locations SET deleted_at=UTC_TIMESTAMP(6),version=? WHERE id=?',[$version,$id]);(new ChangeService($this->app))->record($user['workspace_id'],'location',$id,'delete',$version,$user['id']);(new AuditService($this->app))->record('location.deleted','location',$id,$location,null);Session::flash('success',View::t('deleted_success'));Response::redirect($request->basePath().'/locations');
    }

    private function groupSpools(array $spools, bool $includeLocation): array
    {
        $groups=[];
        foreach($spools as $spool){$key=(string)$spool['material_id'].($includeLocation?'|'.(string)($spool['location_id']??''):'');if(!isset($groups[$key]))$groups[$key]=['material_id'=>$spool['material_id'],'material_type'=>$spool['material_type'],'commercial_name'=>$spool['commercial_name'],'color_name'=>$spool['color_name'],'color_hex'=>$spool['color_hex'],'manufacturer_name'=>$spool['manufacturer_name'],'location_id'=>$spool['location_id']??null,'location_name'=>$spool['location_name']??null,'location_code'=>$spool['location_code']??null,'spool_count'=>0,'available_count'=>0,'loaded_count'=>0,'total_weight_g'=>0.0];$groups[$key]['spool_count']++;$groups[$key]['total_weight_g']+=(float)$spool['current_net_weight_g'];$status=$spool['display_status']??$spool['status']??'in_stock';if($status==='loaded')$groups[$key]['loaded_count']++;elseif($status==='in_stock')$groups[$key]['available_count']++;}
        return array_values($groups);
    }

    private function values(array $rows, string $key): array
    {
        $values=array_values(array_unique(array_filter(array_map(static fn(array $row):string=>(string)($row[$key]??''),$rows),static fn(string $value):bool=>$value!=='')));natcasesort($values);return array_values($values);
    }

    private function options(array $rows, string $idKey, string $labelKey): array
    {
        $options=[];foreach($rows as $row){$id=(string)($row[$idKey]??'');$label=(string)($row[$labelKey]??'');if($id!==''&&$label!=='')$options[$id]=$label;}natcasesort($options);return $options;
    }
}
