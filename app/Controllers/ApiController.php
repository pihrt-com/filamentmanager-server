<?php

declare(strict_types=1);

namespace FilamentManager\Controllers;

use FilamentManager\Core\App; use FilamentManager\Core\Request; use FilamentManager\Core\Response;

final class ApiController
{
    public function __construct(private readonly App $app){}
    public function info(Request $r):void{Response::json(['name'=>$this->app->config('name'),'version'=>trim((string)file_get_contents(FM_ROOT.'/VERSION')),'apiVersion'=>'v1','minimumAppVersion'=>'1.0.0','features'=>['sync','printers','multiSlot','spools','inventory','openPrintTag','backup','printJobs','emailNotifications']]);}
    public function snapshot(Request $r):void{$u=$GLOBALS['fm_api_user'];$w=$u['workspace_id'];$db=$this->app->db();Response::json(['cursor'=>(int)($db->fetch('SELECT COALESCE(MAX(`sequence`),0) AS `cursor` FROM sync_changes WHERE workspace_id=?',[$w])['cursor']??0),'printers'=>$db->fetchAll('SELECT * FROM printers WHERE workspace_id=? AND deleted_at IS NULL ORDER BY sort_order,name',[$w]),'printerSlots'=>$db->fetchAll('SELECT * FROM printer_slots WHERE workspace_id=? AND deleted_at IS NULL ORDER BY printer_id,slot_number',[$w]),'manufacturers'=>$db->fetchAll('SELECT * FROM manufacturers WHERE workspace_id=? AND deleted_at IS NULL',[$w]),'materials'=>$db->fetchAll('SELECT * FROM materials WHERE workspace_id=? AND deleted_at IS NULL',[$w]),'spools'=>$db->fetchAll('SELECT * FROM spools WHERE workspace_id=? AND deleted_at IS NULL',[$w]),'locations'=>$db->fetchAll('SELECT * FROM locations WHERE workspace_id=? AND deleted_at IS NULL',[$w])]);}
}
