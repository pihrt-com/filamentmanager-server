<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\Csrf;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Request;
use FilamentManager\Core\Response;
use FilamentManager\Core\Session;
use FilamentManager\Core\View;
use FilamentManager\Services\AuditService;
use FilamentManager\Services\GcodeParser;
use FilamentManager\Services\NotificationService;
use FilamentManager\Services\PrintJobService;

final class PrintJobController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $request): void
    {
        $user=$this->app->auth()->requireUser();$jobs=$this->app->db()->fetchAll('SELECT j.*,p.name printer_name,u.display_name imported_by FROM print_jobs j JOIN printers p ON p.id=j.printer_id LEFT JOIN users u ON u.id=j.imported_by_user_id WHERE j.workspace_id=? ORDER BY j.created_at DESC LIMIT 250',[$user['workspace_id']]);$printers=$this->app->db()->fetchAll('SELECT id,name FROM printers WHERE workspace_id=? AND deleted_at IS NULL ORDER BY name',[$user['workspace_id']]);View::render('print_jobs',['title'=>View::t('print_jobs'),'jobs'=>$jobs,'printers'=>$printers,'canEdit'=>$user['role']!=='viewer','basePath'=>$request->basePath()]);
    }

    public function import(Request $request): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager','operator');$file=$request->file('gcode');if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)<1||($file['size']??0)>100*1024*1024)throw new HttpException('Invalid G-code upload',422);$name=(string)($file['name']??'print.gcode');if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['gcode','bgcode'],true))throw new HttpException('Only .gcode and .bgcode files are supported',422);try{$parsed=(new GcodeParser())->parse((string)$file['tmp_name']);$id=(new PrintJobService($this->app))->create($user['workspace_id'],(string)$request->input('printer_id'),$name,'upload',$parsed,$user['id']);(new AuditService($this->app))->record('print_job.imported','print_job',$id,null,['file'=>$name,'weight'=>$parsed['totalWeightG']]);Session::flash('success',View::t('gcode_imported'));Response::redirect($request->basePath().'/print-jobs/'.$id);}catch(\Throwable $error){Session::flash('error',View::t('gcode_import_failed',['message'=>$error->getMessage()]));Response::redirect($request->basePath().'/print-jobs');}
    }

    public function detail(Request $request,string $id): void
    {
        $user=$this->app->auth()->requireUser();$job=$this->app->db()->fetch('SELECT j.*,p.name printer_name,u.display_name imported_by FROM print_jobs j JOIN printers p ON p.id=j.printer_id LEFT JOIN users u ON u.id=j.imported_by_user_id WHERE j.id=? AND j.workspace_id=?',[$id,$user['workspace_id']]);if(!$job)throw new HttpException('Print job not found',404);$items=$this->app->db()->fetchAll('SELECT c.*,m.material_type spool_material,m.color_name spool_color FROM print_job_consumptions c LEFT JOIN spools s ON s.id=c.spool_id LEFT JOIN materials m ON m.id=s.material_id WHERE c.job_id=? ORDER BY c.extruder_index',[$id]);$spools=$this->app->db()->fetchAll("SELECT s.id,s.current_net_weight_g,s.status,m.material_type,m.color_name,p.name printer_name,ps.slot_number FROM spools s JOIN materials m ON m.id=s.material_id LEFT JOIN printer_slots ps ON ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL LEFT JOIN printers p ON p.id=ps.printer_id AND p.deleted_at IS NULL WHERE s.workspace_id=? AND s.deleted_at IS NULL AND s.status IN ('in_stock','loaded') AND (ps.printer_id IS NULL OR ps.printer_id=?) ORDER BY p.name IS NULL,p.name,ps.slot_number,m.material_type,m.color_name",[$user['workspace_id'],$job['printer_id']]);View::render('print_job_detail',['title'=>View::t('print_job_detail'),'job'=>$job,'items'=>$items,'spools'=>$spools,'canEdit'=>$user['role']!=='viewer','canDelete'=>in_array($user['role'],['admin','manager'],true),'basePath'=>$request->basePath()]);
    }

    public function update(Request $request,string $id): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager','operator');(new PrintJobService($this->app))->update($user['workspace_id'],$id,(array)$request->input('assignments',[]),(string)$request->input('status','ready'));(new AuditService($this->app))->record('print_job.updated','print_job',$id);Session::flash('success',View::t('saved'));Response::redirect($request->basePath().'/print-jobs/'.$id);
    }

    public function complete(Request $request,string $id): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager','operator');$service=new PrintJobService($this->app);$service->update($user['workspace_id'],$id,(array)$request->input('assignments',[]),'printing');$service->complete($user['workspace_id'],$id,$user['id']);(new NotificationService($this->app))->evaluateWorkspace($user['workspace_id']);(new AuditService($this->app))->record('print_job.completed','print_job',$id);Session::flash('success',View::t('print_job_completed'));Response::redirect($request->basePath().'/print-jobs/'.$id);
    }

    public function delete(Request $request,string $id): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin','manager');$job=$this->app->db()->fetch('SELECT id,source_file_name,status,total_estimated_weight_g FROM print_jobs WHERE id=? AND workspace_id=?',[$id,$user['workspace_id']]);if(!$job)throw new HttpException('Print job not found',404);$this->app->db()->execute('DELETE FROM print_jobs WHERE id=? AND workspace_id=?',[$id,$user['workspace_id']]);(new AuditService($this->app))->record('print_job.deleted','print_job',$id,$job,null);Session::flash('success',View::t('print_job_deleted'));Response::redirect($request->basePath().'/print-jobs');
    }
}
