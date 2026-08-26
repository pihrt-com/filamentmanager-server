<?php

declare(strict_types=1);

namespace FilamentManager\Core;

use PDO;

final class Migrator
{
    public static function run(PDO $pdo): array
    {
        $applied = [];
        $files = glob(FM_ROOT . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $version = basename($file, '.php');
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY, applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
            $check->execute([$version]);
            if ($check->fetchColumn()) continue;
            $statements = require $file;
            try {
                foreach ($statements as $statement) $pdo->exec($statement);
                $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
                $insert->execute([$version]);
                $applied[] = $version;
            } catch (\Throwable $e) {
                throw $e;
            }
        }
        return $applied;
    }
}
