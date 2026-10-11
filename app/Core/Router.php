<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,path:string,regex:string,handler:mixed,middleware:array,params:array}> */
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];
    private array $middlewareMap = [];
    private array $notFound = [];

    public function middleware(string $name, callable $handler): void
    {
        $this->middlewareMap[$name] = $handler;
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        $this->prefix = $previousPrefix . rtrim((string) ($attributes['prefix'] ?? ''), '/');
        $this->groupMiddleware = array_merge($previousMiddleware, (array) ($attributes['middleware'] ?? []));
        $callback($this);
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $path, mixed $handler, array $middleware = []): void    { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, mixed $handler, array $middleware = []): void   { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, mixed $handler, array $middleware = []): void    { $this->add('PUT', $path, $handler, $middleware); }
    public function delete(string $path, mixed $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    public function add(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $full = $this->prefix . '/' . trim($path, '/');
        $full = '/' . trim($full, '/');
        $params = [];
        // /vehicles/{id}/edit  ->  #^/vehicles/(?P<id>[^/]+)/edit$#
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $full);

        $this->routes[] = [
            'method'     => $method,
            'path'       => $full,
            'regex'      => '#^' . $regex . '$#u',
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'params'     => $params,
        ];
    }

    public function fallback(callable $handler): void
    {
        $this->notFound = [$handler];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $arguments = [];
            foreach ($route['params'] as $name) {
                $arguments[$name] = $matches[$name] ?? null;
            }
            $this->runStack($route['middleware'], $request, static function () use ($route, $arguments, $request): void {
                self::invoke($route['handler'], $arguments, $request);
            });
            return;
        }

        if ($allowed !== []) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_unique($allowed)));
            }
            $this->renderError(405, lang_key: 'http.405');
            return;
        }

        $this->renderError(404, lang_key: 'http.404');
    }

    /** Middleware zincirini sirayla calistirir; false donen middleware zinciri durdurur. */
    private function runStack(array $middleware, Request $request, callable $destination): void
    {
        $stack = array_reverse($middleware);
        $next = $destination;
        foreach ($stack as $name) {
            $current = $next;
            $handler = $this->middlewareMap[$name] ?? null;
            if ($handler === null) {
                Logger::error('Tanimsiz middleware: ' . (string) $name);
                continue;
            }
            $next = static function () use ($handler, $request, $current): void {
                $result = $handler($request, $current);
                if ($result === false) {
                    return;
                }
                if (is_string($result)) {
                    echo $result;
                }
            };
        }
        $next();
    }

    private static function invoke(mixed $handler, array $arguments, Request $request): void
    {
        if ($handler instanceof \Closure) {
            echo (string) $handler($request, ...array_values($arguments));
            return;
        }
        if (is_array($handler) && is_string($handler[0])) {
            $controller = new $handler[0]();
            $method = (string) $handler[1];
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Controller metodu bulunamadi: {$handler[0]}::{$method}");
            }
            $reflection = new \ReflectionMethod($controller, $method);
            $callArgs = [];
            foreach ($reflection->getParameters() as $parameter) {
                $name = $parameter->getName();
                $callArgs[] = $arguments[$name] ?? ($parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null);
            }
            echo (string) $controller->$method(...$callArgs);
            return;
        }
        throw new \RuntimeException('Gecersiz route handler.');
    }

    private function renderError(int $status, string $lang_key): void
    {
        http_response_code($status);
        $handler = $this->notFound;
        if ($handler !== []) {
            echo (string) ($handler[0])($status);
            return;
        }
        echo View::render('errors/' . $status, ['title' => t($lang_key)], null);
    }

    /** Tüm kayıtlı rotalar (debug/CLI listesi için). */
    public function routes(): array
    {
        return $this->routes;
    }
}
