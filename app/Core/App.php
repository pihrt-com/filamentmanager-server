<?php

declare(strict_types=1);

namespace FilamentManager\Core;

use PDO;
use RuntimeException;
use Throwable;

final class App
{
    private ?Database $database = null;
    private ?Auth $auth = null;
    private ?Translator $translator = null;

    public function __construct(private readonly array $config)
    {
        date_default_timezone_set((string) ($config['timezone'] ?? 'UTC'));
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        $value = $this->config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public function installed(): bool
    {
        return is_file(FM_ROOT . '/storage/installed.lock') && is_file(FM_ROOT . '/config/local.php');
    }

    public function db(): Database
    {
        if ($this->database === null) {
            $db = $this->config('database');
            if (!is_array($db)) {
                throw new RuntimeException('Database configuration is missing.');
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset'] ?? 'utf8mb4');
            $pdo = new PDO($dsn, (string) $db['username'], (string) $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->database = new Database($pdo);
        }
        return $this->database;
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this);
    }

    public function translator(): Translator
    {
        return $this->translator ??= new Translator($this);
    }

    public function handle(): void
    {
        $request = Request::capture();
        if (!$this->installed()) {
            if (!str_starts_with($request->path(), '/install')) {
                Response::redirect($request->basePath() . '/install/');
            }
            return;
        }

        if (is_file(FM_ROOT . '/storage/maintenance.lock') && !str_starts_with($request->path(), '/api/v1/server-info')) {
            http_response_code(503);
            header('Retry-After: 60');
            exit('FilamentManager Server is being updated. Please try again shortly.');
        }

        Session::start($this);
        View::setApp($this);
        SecurityHeaders::send($request->isHttps());
        $router = require FM_ROOT . '/routes/web.php';
        require FM_ROOT . '/routes/api.php';

        try {
            $router->dispatch($request, $this);
        } catch (HttpException $e) {
            Response::error($request, $e->getMessage(), $e->status());
        } catch (Throwable $e) {
            Logger::error($e);
            Response::error($request, 'Internal server error', 500);
        }
    }
}
