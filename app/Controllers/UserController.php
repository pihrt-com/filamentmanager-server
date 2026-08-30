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

final class UserController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $request,?string $id=null): void
    {
        $user=$this->app->auth()->requireRole('admin');$editing=$id?$this->app->db()->fetch('SELECT u.id,u.username,u.email,u.display_name,u.role,u.locale,u.is_active,n.enabled notification_enabled,n.notify_spool_empty,n.notify_low_spool_weight,n.low_spool_weight_g,n.notify_material_out,n.notify_low_material_count,n.low_material_count,n.notify_location_full FROM users u LEFT JOIN user_notification_settings n ON n.user_id=u.id WHERE u.id=? AND u.workspace_id=? AND u.deleted_at IS NULL',[$id,$user['workspace_id']]):null;if($id&&!$editing)throw new HttpException('User not found',404);$users=$this->app->db()->fetchAll('SELECT u.id,u.username,u.email,u.display_name,u.role,u.locale,u.is_active,u.last_login_at,u.created_at,COALESCE(n.enabled,0) notification_enabled FROM users u LEFT JOIN user_notification_settings n ON n.user_id=u.id WHERE u.workspace_id=? AND u.deleted_at IS NULL ORDER BY u.username',[$user['workspace_id']]);View::render('users',['title'=>View::t('users'),'users'=>$users,'editing'=>$editing,'basePath'=>$request->basePath()]);
    }

    public function save(Request $request,?string $id=null): void
    {
        Csrf::verify($request);$admin=$this->app->auth()->requireRole('admin');$username=trim((string)$request->input('username'));$email=trim((string)$request->input('email'))?:null;$password=(string)$request->input('password');$role=(string)$request->input('role');$notifications=$request->input('notification_enabled')==='1';if(!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)||(!$id&&strlen($password)<12)||($password!==''&&strlen($password)<12)||!in_array($role,['admin','manager','operator','viewer'],true)||($email!==null&&!filter_var($email,FILTER_VALIDATE_EMAIL))||($notifications&&$email===null))throw new HttpException('Invalid user data',422);if($this->app->db()->fetch('SELECT id FROM users WHERE workspace_id=? AND id<>? AND (username=? OR (email IS NOT NULL AND email=?)) LIMIT 1',[$admin['workspace_id'],$id??'',$username,$email])){Session::flash('error',View::t('user_exists'));Response::redirect($request->basePath().($id?'/admin/users/'.$id.'/edit':'/admin/users'));}$locale=in_array($request->input('locale'),['cs','en'],true)?$request->input('locale'):'cs';$displayName=trim((string)$request->input('display_name'))?:$username;$before=null;$this->app->db()->transaction(function($db)use(&$id,&$before,$admin,$username,$email,$displayName,$role,$locale,$password,$notifications,$request):void{if($id){$before=$db->fetch('SELECT * FROM users WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$admin['workspace_id']]);if(!$before)throw new HttpException('User not found',404);if($id===$admin['id'])$role='admin';$params=[$username,$email,$displayName,$role,$locale];$sql='UPDATE users SET username=?,email=?,display_name=?,role=?,locale=?';if($password!==''){$sql.=',password_hash=?';$params[]=password_hash($password,PASSWORD_DEFAULT);}$sql.=' WHERE id=?';$params[]=$id;$db->execute($sql,$params);}else{$id=Uuid::v4();$db->execute('INSERT INTO users(id,workspace_id,username,email,display_name,password_hash,role,locale) VALUES(?,?,?,?,?,?,?,?)',[$id,$admin['workspace_id'],$username,$email,$displayName,password_hash($password,PASSWORD_DEFAULT),$role,$locale]);}$lowWeight=max(0,min(100000,(float)$request->input('low_spool_weight_g',100)));$lowCount=max(0,min(9999,(int)$request->input('low_material_count',1)));$db->execute('INSERT INTO user_notification_settings(user_id,workspace_id,enabled,notify_spool_empty,notify_low_spool_weight,low_spool_weight_g,notify_material_out,notify_low_material_count,low_material_count,notify_location_full) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),notify_spool_empty=VALUES(notify_spool_empty),notify_low_spool_weight=VALUES(notify_low_spool_weight),low_spool_weight_g=VALUES(low_spool_weight_g),notify_material_out=VALUES(notify_material_out),notify_low_material_count=VALUES(notify_low_material_count),low_material_count=VALUES(low_material_count),notify_location_full=VALUES(notify_location_full)',[$id,$admin['workspace_id'],$notifications?1:0,$request->input('notify_spool_empty')==='1'?1:0,$request->input('notify_low_spool_weight')==='1'?1:0,$lowWeight,$request->input('notify_material_out')==='1'?1:0,$request->input('notify_low_material_count')==='1'?1:0,$lowCount,$request->input('notify_location_full')==='1'?1:0]);if($email===null)$db->execute("DELETE FROM mail_queue WHERE user_id=? AND status IN ('queued','failed')",[$id]);else $db->execute("UPDATE mail_queue SET recipient=? WHERE user_id=? AND status IN ('queued','failed')",[$email,$id]);if(!$notifications)$db->execute('UPDATE notification_states SET is_active=0,resolved_at=UTC_TIMESTAMP(6) WHERE user_id=? AND is_active=1',[$id]);});$after=$this->app->db()->fetch('SELECT id,username,email,display_name,role,locale,is_active FROM users WHERE id=?',[$id]);(new AuditService($this->app))->record($before?'user.updated':'user.created','user',$id,$before,$after);Session::flash('success',View::t('saved'));Response::redirect($request->basePath().'/admin/users');
    }

    public function toggle(Request $request,string $id): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin');if($id===$user['id']){Session::flash('error',View::t('cannot_disable_self'));Response::redirect($request->basePath().'/admin/users');}$target=$this->app->db()->fetch('SELECT id,username,is_active FROM users WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$user['workspace_id']]);if(!$target){Session::flash('error',View::t('user_not_found'));Response::redirect($request->basePath().'/admin/users');}$active=(int)!((bool)$target['is_active']);$this->app->db()->execute('UPDATE users SET is_active=? WHERE id=?',[$active,$id]);if(!$active)$this->app->db()->execute('UPDATE devices SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL',[$id]);(new AuditService($this->app))->record('user.status_changed','user',$id,['is_active'=>$target['is_active']],['is_active'=>$active]);Session::flash('success',View::t('saved'));Response::redirect($request->basePath().'/admin/users');
    }

    public function delete(Request $request,string $id): void
    {
        Csrf::verify($request);$user=$this->app->auth()->requireRole('admin');if($id===$user['id']){Session::flash('error',View::t('cannot_delete_self'));Response::redirect($request->basePath().'/admin/users');}$target=$this->app->db()->fetch('SELECT id,username,email,display_name,role,is_active FROM users WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$user['workspace_id']]);if(!$target){Session::flash('error',View::t('user_not_found'));Response::redirect($request->basePath().'/admin/users');}$passwordHash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);$this->app->db()->transaction(function($db)use($id,$passwordHash):void{$db->execute('UPDATE devices SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL',[$id]);$db->execute("UPDATE users SET username=CONCAT('deleted-',id),email=NULL,display_name='Deleted user',password_hash=?,role='viewer',is_active=0,deleted_at=UTC_TIMESTAMP(6) WHERE id=?",[$passwordHash,$id]);});(new AuditService($this->app))->record('user.deleted','user',$id,$target,null);Session::flash('success',View::t('deleted_success'));Response::redirect($request->basePath().'/admin/users');
    }
}
