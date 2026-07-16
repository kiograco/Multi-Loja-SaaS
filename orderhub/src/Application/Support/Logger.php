<?php

declare(strict_types=1);

namespace OrderHub\Application\Support;

/**
 * Minimal logging port so application code never binds to a concrete logger.
 */
interface Logger
{
    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;
}
