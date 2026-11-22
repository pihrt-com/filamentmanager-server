<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App; use FilamentManager\Core\HttpException; use FilamentManager\Core\Request; use FilamentManager\Core\Response; use FilamentManager\Services\RestoreCredentialService; use FilamentManager\Services\TokenService;

final class ApiAuthController
{
    public function __construct(private readonly App $app){}
    public function login(Request $r):void{$username=trim((string)$r->input('username'));$password=(string)$r->input('password');if($username===''||$password==='')throw new HttpException('Username and password are required',422);Response::json((new TokenService($this->app))->login($username,$password,trim((string)$r->input('deviceName','Android device')),trim((string)$r->input('appVersion','')),trim((string)$r->input('deviceId',''))),201);}
    public function refresh(Request $r):void{$token=(string)$r->input('refreshToken');if($token==='')throw new HttpException('Refresh token is required',422);Response::json((new TokenService($this->app))->refresh($token));}
    public function logout(Request $r):void{$token=(string)$r->input('refreshToken');if($token!=='')(new TokenService($this->app))->logout($token);Response::json(['ok'=>true]);}
    public function restoreRegistrationOptions(Request $r):void{$user=$GLOBALS['fm_api_user'];Response::json((new RestoreCredentialService($this->app))->registrationOptions($user));}
    public function restoreRegistrationComplete(Request $r):void{$user=$GLOBALS['fm_api_user'];$credential=$r->input('credential');if(!is_array($credential))throw new HttpException('Restore credential is required',422);(new RestoreCredentialService($this->app))->completeRegistration($user,$credential);Response::json(['ok'=>true],201);}
    public function restoreAuthenticationOptions(Request $r):void{$userId=trim((string)$r->input('userId'));$user=$this->app->db()->fetch('SELECT id,username,display_name FROM users WHERE id=? AND is_active=1 AND deleted_at IS NULL LIMIT 1',[$userId]);if(!$user)throw new HttpException('Restore credential is unavailable',404);Response::json((new RestoreCredentialService($this->app))->authenticationOptions($user));}
    public function restoreAuthenticationComplete(Request $r):void{$userId=trim((string)$r->input('userId'));$credential=$r->input('credential');if(!is_array($credential))throw new HttpException('Restore credential is required',422);$user=(new RestoreCredentialService($this->app))->authenticate($userId,$credential);Response::json((new TokenService($this->app))->restore((string)$user['id'],trim((string)$r->input('deviceName','Android device')),trim((string)$r->input('appVersion','')),trim((string)$r->input('deviceId',''))),201);}
}
