<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\Logger;

final class AuditService
{
    public function __construct(private readonly App $app) {}

    public function record(string $action, ?string $entityType = null, ?string $entityId = null, ?array $before = null, ?array $after = null, ?array $actor = null): void
    {
        $actor ??= $this->app->auth()->user();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ipHash = $ip === '' ? null : hash_hmac('sha256', $ip, (string) $this->app->config('app_key'));
        $this->app->db()->execute(
            'INSERT INTO audit_log (workspace_id,user_id,action,entity_type,entity_id,before_data,after_data,ip_hash,request_id) VALUES (?,?,?,?,?,?,?,?,?)',
            [$actor['workspace_id'] ?? null, $actor['id'] ?? null, $action, $entityType, $entityId, $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, $after ? json_encode($after, JSON_THROW_ON_ERROR) : null, $ipHash, Logger::requestId()]
        );
    }
}
