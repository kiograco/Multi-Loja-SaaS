<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Http;

/**
 * Tiny regex-based router for the Web channel — same shape as
 * Api\Http\Router (routes carry a `protected` flag, path params like {id} are
 * captured as request attributes), kept as its own class so its phpdoc
 * callable signature is typed to WebRequest/WebResponse instead of the API's
 * JSON Request/Response.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, params: list<string>, handler: callable(WebRequest): WebResponse, protected: bool}> */
    private array $routes = [];

    /**
     * @param callable(WebRequest): WebResponse $handler
     */
    public function add(string $method, string $pattern, callable $handler, bool $protected = true): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]+)\}#', static function (array $m) use (&$params): string {
            $params[] = $m[1];

            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'protected' => $protected,
        ];
    }

    /**
     * @return array{handler: callable(WebRequest): WebResponse, params: array<string, string>, protected: bool}|null
     */
    public function match(WebRequest $request): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (preg_match($route['regex'], $request->path, $m) === 1) {
                $params = [];
                foreach ($route['params'] as $i => $name) {
                    $params[$name] = $m[$i + 1];
                }

                return ['handler' => $route['handler'], 'params' => $params, 'protected' => $route['protected']];
            }
        }

        return null;
    }
}
