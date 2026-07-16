<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Http;

/**
 * Immutable snapshot of an incoming Web request. Unlike the API's Request
 * (JSON body only), this one reads regular HTML form submissions ($_POST) and
 * query strings — the two input shapes a server-rendered app actually receives.
 */
final class WebRequest
{
    /**
     * @param array<string, string> $headers lower-cased header names
     * @param array<string, string> $query
     * @param array<string, mixed> $parsedBody
     * @param array<string, string> $attributes route params + auth context
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $headers = [],
        private array $query = [],
        private array $parsedBody = [],
        private array $attributes = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, \PHP_URL_PATH);
        $path = \is_string($path) ? $path : '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        /** @var array<string, string> $query */
        $query = $_GET;
        /** @var array<string, mixed> $body */
        $body = $_POST;

        return new self($method, $path, $headers, $query, $body);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function query(string $name, ?string $default = null): ?string
    {
        return $this->query[$name] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->parsedBody[$key] ?? $default;
    }

    public function attribute(string $key, ?string $default = null): ?string
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = [...$this->attributes, ...$attributes];

        return $clone;
    }

    /**
     * HTMX marks its own requests with this header, letting the controller
     * choose between returning a full page or just the swapped fragment.
     */
    public function isHtmxRequest(): bool
    {
        return $this->header('hx-request') === 'true';
    }
}
