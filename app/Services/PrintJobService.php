<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Uuid;

final class PrintJobService
{
    public function __construct(private readonly App $app) {}

    public function create(string $workspaceId,string $printerId,string $fileName,string $source,array $parsed,?string $userId=null,?string $tokenId=null): string
    {
        $printer=$this->app->db()->fetch('SELECT id FROM printers WHERE id=? AND workspace_id=? AND deleted_at IS NULL',[$printerId,$workspaceId]);if(!$printer)throw new HttpException('Printer not found',422);$consumptions=$this->normaliseConsumptions($parsed['consumptions']??[]);if(!$consumptions)throw new HttpException('No filament consumption was supplied',422);$jobId=Uuid::v4();$slots=$this->app->db()->fetchAll('SELECT slot_number,loaded_spool_id FROM printer_slots WHERE printer_id=? AND deleted_at IS NULL ORDER BY slot_number',[$printerId]);$spools=[];foreach($slots as $slot)$spools[(int)$slot['slot_number']-1]=$slot['loaded_spool_id'];$total=array_sum(array_column($consumptions,'estimatedWeightG'));
        $this->app->db()->transaction(function($db)use($jobId,$workspaceId,$printerId,$fileName,$source,$parsed,$userId,$tokenId,$consumptions,$spools,$total):void{$db->execute('INSERT INTO print_jobs(id,workspace_id,printer_id,source,source_file_name,source_sha256,total_estimated_weight_g,metadata_json,imported_by_user_id,integration_token_id) VALUES(?,?,?,?,?,?,?,?,?,?)',[$jobId,$workspaceId,$printerId,$source,mb_substr(basename($fileName),0,255),strtolower((string)($parsed['sha256']??hash('sha256',json_encode($parsed)))),$total,json_encode($parsed['metadata']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$userId,$tokenId]);foreach($consumptions as $item)$db->execute('INSERT INTO print_job_consumptions(id,job_id,extruder_index,material_type,color_hex,spool_id,estimated_weight_g) VALUES(?,?,?,?,?,?,?)',[Uuid::v4(),$jobId,$item['extruderIndex'],$item['materialType'],$item['colorHex'],$spools[$item['extruderIndex']]??null,$item['estimatedWeightG']]);});return $jobId;
    }

    public function update(string $workspaceId,string $jobId,array $assignments,string $status): void
    {
        if(!in_array($status,['ready','printing','failed','cancelled'],true))throw new HttpException('Invalid print job status',422);$job=$this->app->db()->fetch('SELECT * FROM print_jobs WHERE id=? AND workspace_id=?',[$jobId,$workspaceId]);if(!$job)throw new HttpException('Print job not found',404);if(in_array($job['status'],['completed','cancelled','failed'],true))throw new HttpException('The print job is already closed',409);
        $seen=[];foreach($assignments as $id=>$values){$spoolId=trim((string)($values['spool_id']??''))?:null;if($spoolId){if(isset($seen[$spoolId]))throw new HttpException('Each extruder must use a different spool',422);$seen[$spoolId]=true;$spool=$this->app->db()->fetch('SELECT s.id,ps.printer_id loaded_printer_id FROM spools s LEFT JOIN printer_slots ps ON ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL WHERE s.id=? AND s.workspace_id=? AND s.deleted_at IS NULL',[$spoolId,$workspaceId]);if(!$spool||($spool['loaded_printer_id']!==null&&$spool['loaded_printer_id']!==$job['printer_id']))throw new HttpException('Invalid spool assignment',422);}$actual=trim((string)($values['actual_weight_g']??''));$actual=$actual===''?null:(float)$actual;if($actual!==null&&($actual<0||$actual>100000))throw new HttpException('Invalid actual filament weight',422);$this->app->db()->execute('UPDATE print_job_consumptions c JOIN print_jobs j ON j.id=c.job_id SET c.spool_id=?,c.actual_weight_g=? WHERE c.id=? AND c.job_id=? AND j.workspace_id=?',[$spoolId,$actual,$id,$jobId,$workspaceId]);}
        $started=$status==='printing'?'COALESCE(started_at,UTC_TIMESTAMP(6))':'started_at';$this->app->db()->execute('UPDATE print_jobs SET status=?,started_at='.$started.' WHERE id=?',[$status,$jobId]);
    }

    public function complete(string $workspaceId,string $jobId,string $userId): void
    {
        $this->app->db()->transaction(function($db)use($workspaceId,$jobId,$userId):void{$job=$db->fetch('SELECT * FROM print_jobs WHERE id=? AND workspace_id=? FOR UPDATE',[$jobId,$workspaceId]);if(!$job)throw new HttpException('Print job not found',404);if($job['status']==='completed')throw new HttpException('The print job was already completed',409);if(in_array($job['status'],['failed','cancelled'],true))throw new HttpException('A closed print job cannot be completed',409);$items=$db->fetchAll('SELECT * FROM print_job_consumptions WHERE job_id=? ORDER BY extruder_index FOR UPDATE',[$jobId]);$seen=[];foreach($items as $item){if(!$item['spool_id'])throw new HttpException('Assign a spool to every used extruder before completing the print',422);if(isset($seen[$item['spool_id']]))throw new HttpException('Each extruder must use a different spool',422);$seen[$item['spool_id']]=true;$spool=$db->fetch('SELECT * FROM spools WHERE id=? AND workspace_id=? AND deleted_at IS NULL FOR UPDATE',[$item['spool_id'],$workspaceId]);if(!$spool)throw new HttpException('An assigned spool no longer exists',409);$used=$item['actual_weight_g']!==null?(float)$item['actual_weight_g']:(float)$item['estimated_weight_g'];$before=(float)$spool['current_net_weight_g'];$after=max(0,$before-$used);$status=$spool['status']==='loaded'?'loaded':($after<=0?'empty':$spool['status']);$version=(int)$spool['version']+1;$db->execute('UPDATE spools SET current_net_weight_g=?,status=?,version=? WHERE id=?',[$after,$status,$version,$spool['id']]);$db->execute('UPDATE print_job_consumptions SET actual_weight_g=?,weight_before_g=?,weight_after_g=? WHERE id=?',[$used,$before,$after,$item['id']]);$db->execute('INSERT INTO spool_movements(id,workspace_id,spool_id,movement_type,printer_id,weight_before_g,weight_after_g,weight_delta_g,source,user_id,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[Uuid::v4(),$workspaceId,$spool['id'],'consumed',$job['printer_id'],$before,$after,$after-$before,'import',$userId,'Print job '.$job['source_file_name']]);(new ChangeService($this->app))->record($workspaceId,'spool',$spool['id'],'upsert',$version,$userId);}$db->execute("UPDATE print_jobs SET status='completed',started_at=COALESCE(started_at,created_at),completed_at=UTC_TIMESTAMP(6) WHERE id=?",[$jobId]);});
    }

    private function normaliseConsumptions(array $items): array
    {
        $result=[];$indexes=[];foreach($items as $item){if(!is_array($item))continue;$index=max(0,min(99,(int)($item['extruderIndex']??count($result))));$weight=round((float)($item['estimatedWeightG']??0),2);if($weight<=0||$weight>100000)continue;if(isset($indexes[$index]))throw new HttpException('Duplicate extruder index',422);$indexes[$index]=true;$color=trim((string)($item['colorHex']??''));$result[]=['extruderIndex'=>$index,'estimatedWeightG'=>$weight,'materialType'=>mb_substr(trim((string)($item['materialType']??'')),0,80)?:null,'colorHex'=>preg_match('/^#[0-9A-Fa-f]{6}$/',$color)?strtoupper($color):null];}usort($result,static fn(array $a,array $b):int=>$a['extruderIndex']<=>$b['extruderIndex']);return $result;
    }
}
