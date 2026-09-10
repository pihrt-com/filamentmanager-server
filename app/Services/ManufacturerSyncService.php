<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\Database;
use FilamentManager\Core\HttpException;

final class ManufacturerSyncService
{
    public static function resolve(Database $db, string $workspaceId, string $id): string
    {
        $alias = $db->fetch('SELECT manufacturer_id FROM manufacturer_aliases WHERE workspace_id=? AND alias_id=?', [$workspaceId, $id]);
        return $alias ? (string)$alias['manufacturer_id'] : $id;
    }

    // Called inside the mutation transaction. Keep aliases independently of
    // mutation receipts: references may arrive in a later batch or retry.
    public static function reuse(Database $db, string $workspaceId, string $id, array $data): ?array
    {
        $db->fetch('SELECT id FROM workspaces WHERE id=? FOR UPDATE', [$workspaceId]);
        $resolved = self::resolve($db, $workspaceId, $id);
        if ($resolved === $id && $db->fetch('SELECT id FROM manufacturers WHERE id=? AND workspace_id=?', [$id, $workspaceId])) return null;
        if (!isset($data['name'])) return null;
        $matching = $db->fetch('SELECT * FROM manufacturers WHERE workspace_id=? AND name=? FOR UPDATE', [$workspaceId, trim((string)$data['name'])]);
        if (!$matching) {
            if ($resolved !== $id) throw new HttpException('Manufacturer alias cannot be renamed; synchronize first', 409);
            return null;
        }
        if ($matching['deleted_at'] !== null) throw new HttpException('A deleted manufacturer already uses this name', 409);
        if ($resolved !== $id && $resolved !== $matching['id']) throw new HttpException('Manufacturer alias does not match its name', 409);
        if ($resolved === $id) $db->execute('INSERT INTO manufacturer_aliases(workspace_id,alias_id,manufacturer_id) VALUES(?,?,?)', [$workspaceId, $id, $matching['id']]);
        return $matching;
    }
}
