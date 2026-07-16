<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Http;

/**
 * Immutable HTTP response. `json()` is the primary constructor since the API
 * speaks JSON everywhere.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param mixed $data
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return new self($status, $body, ['content-type' => 'application/json'] + $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function error(string $code, string $message, int $status, array $headers = []): self
    {
        return self::json(['error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }

    public static function noContent(): self
    {
        return new self(204, '', []);
    }

    public static function pdf(string $bytes, string $filename): self
    {
        return new self(200, $bytes, [
            'content-type' => 'application/pdf',
            'content-disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
