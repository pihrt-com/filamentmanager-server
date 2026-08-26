<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App; use FilamentManager\Core\Csrf; use FilamentManager\Core\HttpException; use FilamentManager\Core\Request; use FilamentManager\Core\Response; use FilamentManager\Core\Session; use FilamentManager\Core\Uuid; use FilamentManager\Core\View; use FilamentManager\Services\AuditService;

final class UserController
{
    public function __construct(private readonly App $app){}
    public function index(Request $request):void{$u=$this->app->auth()->requireRole('admin');$users=$this->app->db()->fetchAll('SELECT id,username,email,display_name,role,locale,is_active,last_login_at,created_at FROM users WHERE workspace_id=? AND deleted_at IS NULL ORDER BY username',[$u['workspace_id']]);View::render('users',['title'=>View::t('users'),'users'=>$users,'basePath'=>$request->basePath()]);}
    public function save(Request $request):void{Csrf::verify($request);$u=$this->app->auth()->requireRole('admin');$username=trim((string)$request->input('username'));$password=(string)$request->input('password');$role=(string)$request->input('role');if(!preg_match('/^[A-Za-z0-9_.-]{3,80}$/',$username)||strlen($password)<12||!in_array($role,['admin','manager','operator','viewer'],true))throw new HttpException('Invalid user data',422);$id=Uuid::v4();$this->app->db()->execute('INSERT INTO users(id,workspace_id,username,email,display_name,password_hash,role,locale) VALUES(?,?,?,?,?,?,?,?)',[$id,$u['workspace_id'],$username,trim((string)$request->input('email'))?:null,trim((string)$request->input('display_name'))?:$username,password_hash($password,PASSWORD_DEFAULT),$role,in_array($request->input('locale'),['cs','en'],true)?$request->input('locale'):'cs']);(new AuditService($this->app))->record('user.created','user',$id,null,['username'=>$username,'role'=>$role]);Session::flash('success',View::t('saved'));Response::redirect($request->basePath().'/admin/users');}
    public function toggle(Request $request,string $id):void{Csrf::verify($request);$u=$this->app->auth()->requireRole('admin');if($id===$u['id'])throw new HttpException('You cannot deactivate your own account',422);$target=$this->app->db()->fetch('SELECT id,username,is_active FROM users WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$id,$u['workspace_id']]);if(!$target)throw new HttpException('User not found',404);$active=(int)!((bool)$target['is_active']);$this->app->db()->execute('UPDATE users SET is_active=? WHERE id=?',[$active,$id]);if(!$active)$this->app->db()->execute('UPDATE devices SET revoked_at=UTC_TIMESTAMP(6) WHERE user_id=? AND revoked_at IS NULL',[$id]);(new AuditService($this->app))->record('user.status_changed','user',$id,['is_active'=>$target['is_active']],['is_active'=>$active]);Response::redirect($request->basePath().'/admin/users');}
}
