<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;

final class SettingsService
{
    public function __construct(private readonly App $app) {}

    public function all(string $workspaceId, string $prefix = ''): array
    {
        $sql='SELECT setting_key,setting_value FROM settings WHERE workspace_id=?';$params=[$workspaceId];
        if($prefix!==''){$sql.=' AND setting_key LIKE ?';$params[]=$prefix.'%';}
        $values=[];foreach($this->app->db()->fetchAll($sql,$params) as $row)$values[$row['setting_key']]=$row['setting_value'];return $values;
    }

    public function setMany(string $workspaceId, array $values): void
    {
        foreach($values as $key=>$value)$this->app->db()->execute('INSERT INTO settings(workspace_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$workspaceId,(string)$key,$value===null?null:(string)$value]);
    }
}
