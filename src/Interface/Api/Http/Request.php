<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Http;

/**
 * Immutable snapshot of an incoming HTTP request. Built from PHP superglobals at
 * the edge (fromGlobals) or explicitly in tests, so nothing downstream reads
 * globals directly.
 */
final class Request
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
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $raw = file_get_contents('php://input');
        $parsed = [];
        if (\is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (\is_array($decoded)) {
                $parsed = $decoded;
            }
        }

        /** @var array<string, string> $query */
        $query = $_GET;

        return new self($method, $path, $headers, $query, $parsed);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function query(string $name, ?string $default = null): ?string
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->parsedBody;
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
}
