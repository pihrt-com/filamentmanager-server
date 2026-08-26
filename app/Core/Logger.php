<?php

declare(strict_types=1);

namespace FilamentManager\Core;

use Throwable;

final class Logger
{
    private static ?string $requestId = null;
    public static function requestId(): string { return self::$requestId ??= bin2hex(random_bytes(8)); }
    public static function error(Throwable $e): void
    {
        $line = sprintf("[%s] request=%s %s in %s:%d\n", gmdate('c'), self::requestId(), $e->getMessage(), $e->getFile(), $e->getLine());
        error_log($line, 3, FM_ROOT . '/storage/logs/app.log');
    }
}
