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

final class MaterialController
{
    public function __construct(private readonly App $app) {}
    public function index(Request $request): void
    {
        $user=$this->app->auth()->requireUser();
        $materials=$this->app->db()->fetchAll('SELECT m.*,mf.name manufacturer_name FROM materials m LEFT JOIN manufacturers mf ON mf.id=m.manufacturer_id WHERE m.workspace_id=? AND m.deleted_at IS NULL ORDER BY m.material_type,m.color_name',[$user['workspace_id']]);
        View::render('materials',['title'=>View::t('materials'),'materials'=>$materials,'basePath'=>$request->basePath()]);
    }
    public function save(Request $request): void
    {
        Csrf::verify($request); $user=$this->app->auth()->requireRole('admin','manager');
        $type=strtoupper(trim((string)$request->input('material_type'))); $color=trim((string)$request->input('color_name'));
        if($type===''||mb_strlen($type)>80||$color===''||mb_strlen($color)>120) throw new HttpException('Invalid material',422);
        $hex=trim((string)$request->input('color_hex'));
        if($hex!==''&&!preg_match('/^#[0-9A-Fa-f]{6}$/',$hex)) throw new HttpException('Invalid color',422);
        $manufacturerName=trim((string)$request->input('manufacturer'));
        $manufacturerId=null;
        if($manufacturerName!==''){
            $manufacturer=$this->app->db()->fetch('SELECT id FROM manufacturers WHERE workspace_id=? AND name=? AND deleted_at IS NULL',[$user['workspace_id'],$manufacturerName]);
            if(!$manufacturer){$manufacturerId=Uuid::v4();$this->app->db()->execute('INSERT INTO manufacturers(id,workspace_id,name) VALUES(?,?,?)',[$manufacturerId,$user['workspace_id'],$manufacturerName]);}else{$manufacturerId=$manufacturer['id'];}
        }
        $id=Uuid::v4();
        $this->app->db()->execute('INSERT INTO materials(id,workspace_id,manufacturer_id,material_type,commercial_name,color_name,color_hex,diameter_mm) VALUES(?,?,?,?,?,?,?,?)',[$id,$user['workspace_id'],$manufacturerId,$type,trim((string)$request->input('commercial_name'))?:null,$color,$hex?:null,max(1.0,min(3.0,(float)$request->input('diameter_mm',1.75)))]);
        (new ChangeService($this->app))->record($user['workspace_id'],'material',$id,'upsert',1,$user['id']);
        (new AuditService($this->app))->record('material.created','material',$id,null,['material_type'=>$type,'color_name'=>$color]);
        Session::flash('success',View::t('saved')); Response::redirect($request->basePath().'/materials');
    }
    public function delete(Request $request,string $id):void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager');$material=$this->app->db()->fetch('SELECT * FROM materials WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$user['workspace_id']]);if(!$material)throw new HttpException('Material not found',404);if($this->app->db()->fetch('SELECT id FROM spools WHERE material_id=? AND deleted_at IS NULL LIMIT 1',[$id]))throw new HttpException('Material is used by a spool and cannot be deleted',409);$version=(int)$material['version']+1;$this->app->db()->execute('UPDATE materials SET deleted_at=UTC_TIMESTAMP(6),version=? WHERE id=?',[$version,$id]);(new ChangeService($this->app))->record($user['workspace_id'],'material',$id,'delete',$version,$user['id']);(new AuditService($this->app))->record('material.deleted','material',$id,$material,null);Response::redirect($request->basePath().'/materials');
    }
}
