<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App; use FilamentManager\Core\HttpException; use FilamentManager\Core\Request; use FilamentManager\Core\Response; use FilamentManager\Services\TokenService;

final class ApiAuthController
{
    public function __construct(private readonly App $app){}
    public function login(Request $r):void{$username=trim((string)$r->input('username'));$password=(string)$r->input('password');if($username===''||$password==='')throw new HttpException('Username and password are required',422);Response::json((new TokenService($this->app))->login($username,$password,trim((string)$r->input('deviceName','Android device')),trim((string)$r->input('appVersion','')),trim((string)$r->input('deviceId',''))),201);}
    public function refresh(Request $r):void{$token=(string)$r->input('refreshToken');if($token==='')throw new HttpException('Refresh token is required',422);Response::json((new TokenService($this->app))->refresh($token));}
    public function logout(Request $r):void{$token=(string)$r->input('refreshToken');if($token!=='')(new TokenService($this->app))->logout($token);Response::json(['ok'=>true]);}
}
