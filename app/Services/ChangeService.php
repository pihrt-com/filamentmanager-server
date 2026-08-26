<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;

final class ChangeService
{
    public function __construct(private readonly App $app) {}
    public function record(string $workspaceId, string $type, string $id, string $operation, int $version, ?string $userId = null, ?string $deviceId = null): void
    {
        $this->app->db()->execute('INSERT INTO sync_changes (workspace_id,entity_type,entity_id,operation,entity_version,user_id,device_id) VALUES (?,?,?,?,?,?,?)', [$workspaceId, $type, $id, $operation, $version, $userId, $deviceId]);
    }
}
