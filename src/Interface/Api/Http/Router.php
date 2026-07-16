<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Http;

/**
 * Tiny regex-based router. Routes carry a `protected` flag driving the
 * authentication/rate-limit middleware in the kernel. Path params ({id}) are
 * captured and injected as request attributes.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, params: list<string>, handler: callable(Request): Response, protected: bool}> */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
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
     * @return array{handler: callable(Request): Response, params: array<string, string>, protected: bool}|null
     *                                                                                                          null = no path match; ['method_not_allowed' => true] style handled by caller via matchedPathButMethod
     */
    public function match(Request $request): ?array
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

    public function pathExistsForOtherMethod(Request $request): bool
    {
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path) === 1) {
                return true;
            }
        }

        return false;
    }
}
