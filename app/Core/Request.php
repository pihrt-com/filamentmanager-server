<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Request
{
    private array $json = [];

    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $files,
        private readonly string $rawBody,
    ) {
        if (str_contains(strtolower((string) ($server['CONTENT_TYPE'] ?? '')), 'application/json') && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $this->json = is_array($decoded) ? $decoded : [];
        }
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = '/' . ltrim((string) parse_url($uri, PHP_URL_PATH), '/');
        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        if ($scriptDir !== '/' && $scriptDir !== '.' && str_starts_with($path, $scriptDir)) {
            $path = '/' . ltrim(substr($path, strlen($scriptDir)), '/');
        }
        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), rtrim($path, '/') ?: '/', $_GET, $_POST, $_SERVER, $_FILES, (string) file_get_contents('php://input'));
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function input(string $key, mixed $default = null): mixed { return $this->json[$key] ?? $this->post[$key] ?? $default; }
    public function all(): array { return $this->json ?: $this->post; }
    public function file(string $key): ?array { return isset($this->files[$key]) && is_array($this->files[$key]) ? $this->files[$key] : null; }
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? trim((string) $this->server[$key]) : null;
    }
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        return $header && preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : null;
    }
    public function isApi(): bool { return str_starts_with($this->path, '/api/'); }
    public function isHttps(): bool { return ($this->server['HTTPS'] ?? '') !== '' && ($this->server['HTTPS'] ?? '') !== 'off'; }
    public function basePath(): string
    {
        $dir = str_replace('\\', '/', dirname((string) ($this->server['SCRIPT_NAME'] ?? '/')));
        return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
    }
}
