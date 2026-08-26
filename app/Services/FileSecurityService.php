<?php

declare(strict_types=1);

namespace FilamentManager\Services;

final class FileSecurityService
{
    private const PRIVATE_FILES = ['config/local.php', 'storage/installed.lock'];
    private const PRIVATE_DIRS = ['app', 'config', 'database', 'resources', 'routes', 'storage', 'tests', 'tools'];

    public function audit(): array
    {
        $issues = [];
        foreach (self::PRIVATE_FILES as $relative) {
            $path = FM_ROOT . '/' . $relative;
            if (is_file($path) && (fileperms($path) & 0004)) $issues[] = $relative . ' is readable by other system users.';
        }
        if (!is_file(FM_ROOT . '/storage/installed.lock')) $issues[] = 'The installer lock is missing.';
        if (is_dir(FM_ROOT . '/install')) $issues[] = 'Delete the install directory after installation (the lock still prevents reuse).';
        if (!is_writable(FM_ROOT . '/storage')) $issues[] = 'The storage directory is not writable.';
        return $issues;
    }

    public function harden(): array
    {
        $changed = [];
        foreach (self::PRIVATE_DIRS as $relative) {
            $path = FM_ROOT . '/' . $relative;
            if (is_dir($path) && @chmod($path, 0750)) $changed[] = $relative . '/ = 0750';
        }
        foreach (self::PRIVATE_FILES as $relative) {
            $path = FM_ROOT . '/' . $relative;
            if (is_file($path) && @chmod($path, 0640)) $changed[] = $relative . ' = 0640';
        }
        if (is_dir(FM_ROOT . '/public') && @chmod(FM_ROOT . '/public', 0755)) $changed[] = 'public/ = 0755';
        return $changed;
    }
}
