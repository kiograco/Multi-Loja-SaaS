<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Logging;

use OrderHub\Application\Support\Logger;

/**
 * Writes structured JSON lines to a stream (stderr by default, or a file). Good
 * enough for a project where "send an e-mail" is simulated by a log line.
 */
final class StreamLogger implements Logger
{
    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream
     */
    public function __construct($stream = null)
    {
        $this->stream = $stream ?? \STDERR;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode([
            'ts' => date(\DateTimeImmutable::ATOM),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ], \JSON_THROW_ON_ERROR);
        fwrite($this->stream, $line . \PHP_EOL);
    }
}
