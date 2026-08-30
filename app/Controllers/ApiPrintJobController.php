<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Request;
use FilamentManager\Core\Response;
use FilamentManager\Services\PrintJobService;

final class ApiPrintJobController
{
    public function __construct(private readonly App $app) {}
    public function import(Request $request): void
    {
        $token=$GLOBALS['fm_integration_token'];$printerId=trim((string)$request->input('printerId'));$printerName=trim((string)$request->input('printerName'));if($printerId===''){$printer=$this->app->db()->fetch('SELECT id FROM printers WHERE workspace_id=? AND name=? AND deleted_at IS NULL',[$token['workspace_id'],$printerName]);$printerId=(string)($printer['id']??'');}if($printerId==='')throw new HttpException('A matching printer was not found',422);$sha=strtolower((string)$request->input('sha256'));if(!preg_match('/^[a-f0-9]{64}$/',$sha))throw new HttpException('Invalid G-code SHA-256',422);$parsed=['sha256'=>$sha,'metadata'=>(array)$request->input('metadata',[]),'consumptions'=>(array)$request->input('consumptions',[])];$id=(new PrintJobService($this->app))->create($token['workspace_id'],$printerId,(string)$request->input('fileName','print.gcode'),'prusaslicer',$parsed,null,$token['id']);Response::json(['id'=>$id,'url'=>rtrim((string)$this->app->config('app_url'),'/').'/print-jobs/'.$id],201);
    }
}
