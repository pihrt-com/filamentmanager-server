<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\HttpException;

final class NotificationCronService
{
    public function __construct(private readonly App $app) {}

    public function details(string $workspaceId,string $basePath=''): array
    {
        $settings=(new SettingsService($this->app))->all($workspaceId,'notification_cron_');
        $token=$this->decryptToken((string)($settings['notification_cron_token_encrypted']??''));
        if($token==='')$token=$this->rotate($workspaceId);
        $result=json_decode((string)($settings['notification_cron_last_result']??''),true);
        $appUrl=rtrim((string)$this->app->config('app_url'),'/');$configuredPath=rtrim((string)(parse_url($appUrl,PHP_URL_PATH)??''),'/');if($basePath!==''&&!str_ends_with($configuredPath,$basePath))$appUrl.=('/'.ltrim($basePath,'/'));
        return [
            'url'=>$appUrl.'/cron/notifications/'.$token,
            'lastRunAt'=>$settings['notification_cron_last_run_at']??null,
            'lastResult'=>is_array($result)?$result:null,
        ];
    }

    public function rotate(string $workspaceId): string
    {
        $token=bin2hex(random_bytes(32));
        (new SettingsService($this->app))->setMany($workspaceId,[
            'notification_cron_token_hash'=>hash('sha256',$token),
            'notification_cron_token_encrypted'=>(new CryptoService($this->app))->encrypt($token),
        ]);
        return $token;
    }

    public function run(string $token): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new HttpException('Not found',404);
        $row=$this->app->db()->fetch("SELECT workspace_id FROM settings WHERE setting_key='notification_cron_token_hash' AND setting_value=?",[hash('sha256',$token)]);
        if(!$row)throw new HttpException('Not found',404);
        $workspaceId=(string)$row['workspace_id'];$notifications=new NotificationService($this->app);$queued=$notifications->evaluateWorkspace($workspaceId);$result=['queued'=>$queued]+$notifications->processQueue(100,$workspaceId);
        (new SettingsService($this->app))->setMany($workspaceId,['notification_cron_last_run_at'=>gmdate('Y-m-d H:i:s'),'notification_cron_last_result'=>json_encode($result,JSON_THROW_ON_ERROR)]);
        return $result;
    }

    private function decryptToken(string $encrypted): string
    {
        if($encrypted==='')return '';
        try{$token=(new CryptoService($this->app))->decrypt($encrypted);return preg_match('/^[a-f0-9]{64}$/',$token)?$token:'';}catch(\Throwable){return '';}
    }
}
