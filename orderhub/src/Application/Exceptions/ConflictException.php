<?php

declare(strict_types=1);

namespace OrderHub\Application\Exceptions;

use RuntimeException;

/**
 * Application-level conflict (e.g. duplicate e-mail). Maps to HTTP 409.
 */
final class ConflictException extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
