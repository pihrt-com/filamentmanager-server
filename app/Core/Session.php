<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Session
{
    public static function start(App $app): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name((string) $app->config('session_name', 'filamentmanager_session'));
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => Request::capture()->isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed { return $_SESSION[$key] ?? $default; }
    public static function put(string $key, mixed $value): void { $_SESSION[$key] = $value; }
    public static function forget(string $key): void { unset($_SESSION[$key]); }
    public static function flash(string $key, mixed $value): void { $_SESSION['_flash'][$key] = $value; }
    public static function pullFlash(string $key): mixed { $v = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $v; }
}
