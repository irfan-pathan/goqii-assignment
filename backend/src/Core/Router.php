<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{method:string,regex:string,handler:callable,middleware:list<callable>}> */
    private array $routes = [];

    /** @param list<callable> $middleware callables receiving the Request; they throw HttpException to abort */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middleware'] as $mw) {
                $mw($request);
            }
            ($route['handler'])($request);
            return;
        }
        throw $pathMatched
            ? new HttpException(405, 'Method not allowed.')
            : new HttpException(404, 'Route not found.');
    }
}
