<?php

declare(strict_types=1);

use FilamentManager\Controllers\ApiAuthController;use FilamentManager\Controllers\ApiController;use FilamentManager\Controllers\SyncController;use FilamentManager\Core\HttpException;use FilamentManager\Services\TokenService;

$apiAuth=static function($request,$app):void{$token=$request->bearerToken();if(!$token)throw new HttpException('Bearer token is required',401);$GLOBALS['fm_api_user']=(new TokenService($app))->authenticate($token);};
$router->get('/api/v1/server-info',[ApiController::class,'info']);
$router->post('/api/v1/auth/login',[ApiAuthController::class,'login']);
$router->post('/api/v1/auth/refresh',[ApiAuthController::class,'refresh']);
$router->post('/api/v1/auth/logout',[ApiAuthController::class,'logout']);
$router->get('/api/v1/snapshot',[ApiController::class,'snapshot'],[$apiAuth]);
$router->get('/api/v1/sync/changes',[SyncController::class,'changes'],[$apiAuth]);
$router->post('/api/v1/sync/push',[SyncController::class,'push'],[$apiAuth]);
