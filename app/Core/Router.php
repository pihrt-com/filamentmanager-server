<?php

declare(strict_types=1);

namespace FilamentManager\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, callable|array $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }
    public function delete(string $path, callable|array $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(Request $request, App $app): void
    {
        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', '(?P<$1>[A-Za-z0-9_-]+)', $route['path']);
            if ($route['method'] !== $request->method() || !preg_match('#^' . $pattern . '$#', $request->path(), $matches)) {
                continue;
            }
            foreach ($route['middleware'] as $middleware) {
                $middleware($request, $app);
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler[0] = new $handler[0]($app);
            }
            $handler($request, ...array_values($params));
            return;
        }
        throw new HttpException('Not found', 404);
    }
}
