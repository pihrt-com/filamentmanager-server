<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Auth
{
    private ?array $cachedUser = null;

    public function __construct(private readonly App $app) {}

    public function user(): ?array
    {
        $id = Session::get('user_id');
        if (!is_string($id)) return null;
        if ($this->cachedUser === null) {
            $this->cachedUser = $this->app->db()->fetch('SELECT id, workspace_id, username, email, display_name, role, locale FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$id]);
        }
        return $this->cachedUser;
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->app->db()->fetch('SELECT * FROM users WHERE username = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1', [$username]);
        if (!$user || ($user['locked_until'] && strtotime((string) $user['locked_until']) > time()) || !password_verify($password, (string) $user['password_hash'])) {
            if ($user) {
                $count = (int) $user['failed_login_count'] + 1;
                $lock = $count >= 5 ? gmdate('Y-m-d H:i:s', time() + 900) : null;
                $this->app->db()->execute('UPDATE users SET failed_login_count = ?, locked_until = ? WHERE id = ?', [$count, $lock, $user['id']]);
            }
            usleep(random_int(200000, 450000));
            return false;
        }
        session_regenerate_id(true);
        Session::put('user_id', $user['id']);
        $this->app->db()->execute('UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = UTC_TIMESTAMP(6) WHERE id = ?', [$user['id']]);
        return true;
    }

    public function logout(): void
    {
        Session::forget('user_id');
        Session::forget('_csrf');
        session_regenerate_id(true);
        $this->cachedUser = null;
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if (!$user) throw new HttpException('Authentication required', 401);
        return $user;
    }

    public function requireRole(string ...$roles): array
    {
        $user = $this->requireUser();
        if (!in_array($user['role'], $roles, true)) throw new HttpException('Permission denied', 403);
        return $user;
    }
}
