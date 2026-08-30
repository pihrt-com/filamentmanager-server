<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;

final class NotificationService
{
    public function __construct(private readonly App $app) {}

    public function evaluateAll(): int
    {
        $workspaces=$this->app->db()->fetchAll('SELECT id FROM workspaces');$queued=0;foreach($workspaces as $workspace)$queued+=$this->evaluateWorkspace($workspace['id']);return $queued;
    }

    public function evaluateWorkspace(string $workspaceId): int
    {
        $users=$this->app->db()->fetchAll('SELECT u.id,u.email,u.display_name,u.locale,n.* FROM users u JOIN user_notification_settings n ON n.user_id=u.id WHERE u.workspace_id=? AND u.deleted_at IS NULL AND u.is_active=1 AND u.email IS NOT NULL AND n.enabled=1',[$workspaceId]);$queued=0;foreach($users as $user){$events=$this->events($workspaceId,$user);$activeKeys=[];foreach($events as $event){$activeKeys[]=$event['key'];$state=$this->app->db()->fetch('SELECT id,is_active FROM notification_states WHERE user_id=? AND event_key=?',[$user['id'],$event['key']]);if(!$state){$this->app->db()->execute('INSERT INTO notification_states(workspace_id,user_id,event_key,event_type,context_data,last_queued_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[$workspaceId,$user['id'],$event['key'],$event['type'],json_encode($event['context'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$this->queue($workspaceId,$user,$event);$queued++;}elseif(!(bool)$state['is_active']){$this->app->db()->execute('UPDATE notification_states SET is_active=1,context_data=?,first_triggered_at=UTC_TIMESTAMP(6),last_triggered_at=UTC_TIMESTAMP(6),last_queued_at=UTC_TIMESTAMP(6),resolved_at=NULL WHERE id=?',[json_encode($event['context'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$state['id']]);$this->queue($workspaceId,$user,$event);$queued++;}else{$this->app->db()->execute('UPDATE notification_states SET last_triggered_at=UTC_TIMESTAMP(6),context_data=? WHERE id=?',[json_encode($event['context'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$state['id']]);}}$params=[$user['id']];$sql='UPDATE notification_states SET is_active=0,resolved_at=UTC_TIMESTAMP(6) WHERE user_id=? AND is_active=1';if($activeKeys){$sql.=' AND event_key NOT IN ('.implode(',',array_fill(0,count($activeKeys),'?')).')';$params=array_merge($params,$activeKeys);}$this->app->db()->execute($sql,$params);}return $queued;
    }

    public function processQueue(int $limit=20,?string $workspaceId=null,bool $forceDue=false): array
    {
        $due=$forceDue?"status IN ('queued','failed')":"status IN ('queued','failed') AND next_attempt_at<=UTC_TIMESTAMP(6)";$available="(({$due}) OR (status='sending' AND locked_at<UTC_TIMESTAMP(6)-INTERVAL 10 MINUTE))";$params=[];$workspaceSql='';if($workspaceId!==null){$workspaceSql=' AND workspace_id=?';$params[]=$workspaceId;}$rows=$this->app->db()->fetchAll("SELECT * FROM mail_queue WHERE {$available} AND attempts<5".$workspaceSql.' ORDER BY id LIMIT '.max(1,min(100,$limit)),$params);$sent=0;$failed=0;foreach($rows as $row){try{$claimed=$this->app->db()->execute("UPDATE mail_queue SET status='sending',locked_at=UTC_TIMESTAMP(6),attempts=attempts+1 WHERE id=? AND {$available}",[$row['id']])->rowCount();if($claimed!==1)continue;$config=$this->mailConfig($row['workspace_id']);if(($config['smtp_enabled']??'0')!=='1')throw new \RuntimeException('SMTP delivery is disabled.');(new SmtpMailer())->send($config,$row['recipient'],$row['subject'],$row['body']);$this->app->db()->execute("UPDATE mail_queue SET status='sent',sent_at=UTC_TIMESTAMP(6),locked_at=NULL,last_error=NULL WHERE id=?",[$row['id']]);$sent++;}catch(\Throwable $error){$delay=min(3600,300*((int)$row['attempts']+1));$next=gmdate('Y-m-d H:i:s',time()+$delay);$this->app->db()->execute("UPDATE mail_queue SET status='failed',locked_at=NULL,last_error=?,next_attempt_at=? WHERE id=?",[mb_substr($error->getMessage(),0,500),$next,$row['id']]);$failed++;}}if($workspaceId!==null)$this->pruneHistory($workspaceId);else foreach($this->app->db()->fetchAll('SELECT id FROM workspaces') as $workspace)$this->pruneHistory((string)$workspace['id']);return ['sent'=>$sent,'failed'=>$failed];
    }

    public function pruneHistory(string $workspaceId,int $keep=50): int
    {
        $keep=max(10,min(500,$keep));$recent=$this->app->db()->fetchAll('SELECT id FROM mail_queue WHERE workspace_id=? ORDER BY id DESC LIMIT '.$keep,[$workspaceId]);if(!$recent)return 0;$ids=array_column($recent,'id');$placeholders=implode(',',array_fill(0,count($ids),'?'));return $this->app->db()->execute("DELETE FROM mail_queue WHERE workspace_id=? AND id NOT IN ({$placeholders}) AND (status='sent' OR (status='failed' AND attempts>=5))",array_merge([$workspaceId],$ids))->rowCount();
    }

    public function mailConfig(string $workspaceId): array
    {
        $values=(new SettingsService($this->app))->all($workspaceId,'smtp_');$values['smtp_password']='';if(!empty($values['smtp_password_encrypted']))$values['smtp_password']=(new CryptoService($this->app))->decrypt($values['smtp_password_encrypted']);return $values;
    }

    private function events(string $workspaceId,array $user): array
    {
        $events=[];$spools=$this->app->db()->fetchAll('SELECT s.id,s.current_net_weight_g,s.status,m.material_type,m.color_name,m.commercial_name FROM spools s JOIN materials m ON m.id=s.material_id WHERE s.workspace_id=? AND s.deleted_at IS NULL',[$workspaceId]);foreach($spools as $spool){$name=$this->materialName($spool);if($user['notify_spool_empty']&&($spool['status']==='empty'||(float)$spool['current_net_weight_g']<=0))$events[]=$this->event('spool_empty:'.$spool['id'],'spool_empty',['material'=>$name]);elseif($user['notify_low_spool_weight']&&(float)$spool['current_net_weight_g']>0&&(float)$spool['current_net_weight_g']<(float)$user['low_spool_weight_g'])$events[]=$this->event('spool_low:'.$spool['id'],'spool_low',['material'=>$name,'weight'=>(float)$spool['current_net_weight_g'],'limit'=>(float)$user['low_spool_weight_g']]);}
        $materials=$this->app->db()->fetchAll("SELECT m.id,m.material_type,m.color_name,m.commercial_name,COUNT(s.id) available_count FROM materials m LEFT JOIN spools s ON s.material_id=m.id AND s.deleted_at IS NULL AND s.status='in_stock' AND NOT EXISTS(SELECT 1 FROM printer_slots ps WHERE ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL) WHERE m.workspace_id=? AND m.deleted_at IS NULL AND EXISTS(SELECT 1 FROM spools history WHERE history.material_id=m.id) GROUP BY m.id,m.material_type,m.color_name,m.commercial_name",[$workspaceId]);foreach($materials as $material){$name=$this->materialName($material);$count=(int)$material['available_count'];if($user['notify_material_out']&&$count===0)$events[]=$this->event('material_out:'.$material['id'],'material_out',['material'=>$name]);elseif($user['notify_low_material_count']&&$count<=(int)$user['low_material_count'])$events[]=$this->event('material_low:'.$material['id'],'material_low',['material'=>$name,'count'=>$count,'limit'=>(int)$user['low_material_count']]);}
        if($user['notify_location_full']){$locations=$this->app->db()->fetchAll("SELECT l.id,l.name,l.spool_capacity,COUNT(s.id) occupied FROM locations l LEFT JOIN spools s ON s.location_id=l.id AND s.deleted_at IS NULL AND s.status IN ('in_stock','empty') AND NOT EXISTS(SELECT 1 FROM printer_slots ps WHERE ps.loaded_spool_id=s.id AND ps.deleted_at IS NULL) WHERE l.workspace_id=? AND l.deleted_at IS NULL AND l.spool_capacity IS NOT NULL GROUP BY l.id,l.name,l.spool_capacity HAVING occupied>=l.spool_capacity",[$workspaceId]);foreach($locations as $location)$events[]=$this->event('location_full:'.$location['id'],'location_full',['location'=>$location['name'],'occupied'=>(int)$location['occupied'],'capacity'=>(int)$location['spool_capacity']]);}return $events;
    }

    private function event(string $key,string $type,array $context): array{return compact('key','type','context');}
    private function materialName(array $row): string{return trim((string)$row['material_type'].' '.(string)($row['commercial_name']??'').' · '.(string)$row['color_name']);}
    private function queue(string $workspaceId,array $user,array $event): void{$locale=$user['locale']==='en'?'en':'cs';$texts=$this->text($locale,$event);$this->app->db()->execute('INSERT INTO mail_queue(workspace_id,user_id,recipient,subject,body) VALUES(?,?,?,?,?)',[$workspaceId,$user['id'],$user['email'],$texts['subject'],$texts['body']]);}
    private function text(string $locale,array $event): array{$c=$event['context'];$en=$locale==='en';return match($event['type']){'spool_empty'=>['subject'=>$en?'Filament spool is empty':'Cívka filamentu je prázdná','body'=>($en?'The spool is empty: ':'Cívka je prázdná: ').$c['material']], 'spool_low'=>['subject'=>$en?'Low filament spool weight':'Nízká hmotnost cívky','body'=>($en?'Low spool weight: ':'Nízká hmotnost cívky: ').$c['material'].' — '.$c['weight'].' g ('.($en?'limit ':'limit ').$c['limit'].' g)'], 'material_out'=>['subject'=>$en?'Filament material is out of stock':'Materiál není skladem','body'=>($en?'No available spool remains for: ':'Nezbývá žádná dostupná cívka: ').$c['material']], 'material_low'=>['subject'=>$en?'Low filament stock':'Nízká zásoba filamentu','body'=>($en?'Available spool count is low: ':'Nízký počet dostupných cívek: ').$c['material'].' — '.$c['count'].' ('.($en?'limit ':'limit ').$c['limit'].')'], default=>['subject'=>$en?'Storage location is full':'Skladové místo je plné','body'=>($en?'Storage location is full: ':'Skladové místo je plné: ').$c['location'].' — '.$c['occupied'].'/'.$c['capacity']]};}
}
