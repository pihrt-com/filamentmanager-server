<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;use FilamentManager\Core\HttpException;use FilamentManager\Core\Request;use FilamentManager\Core\Response;use FilamentManager\Services\SyncService;

final class SyncController
{
    public function __construct(private readonly App $app){}
    public function changes(Request $r):void{$u=$GLOBALS['fm_api_user'];Response::json((new SyncService($this->app))->changes($u,max(0,(int)$r->query('after',0)),(int)$r->query('limit',200)));}
    public function push(Request $r):void{$u=$GLOBALS['fm_api_user'];$mutations=$r->input('mutations',[]);if(!is_array($mutations))throw new HttpException('Mutations must be an array',422);Response::json((new SyncService($this->app))->push($u,$mutations));}
}
