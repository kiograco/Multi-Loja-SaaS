<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Http;

/**
 * Immutable HTTP response for the Web channel. Unlike the API's Response
 * (always JSON), this one speaks HTML and redirects — the two things a
 * server-rendered app sends back.
 */
final class WebResponse
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
     * @param array<string, string> $headers
     */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, ['content-type' => 'text/html; charset=UTF-8'] + $headers);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['location' => $location]);
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
