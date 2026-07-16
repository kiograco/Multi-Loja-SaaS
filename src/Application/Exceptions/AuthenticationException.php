<?php

declare(strict_types=1);

namespace OrderHub\Application\Exceptions;

use RuntimeException;

/**
 * Bad credentials or invalid/absent token. Maps to HTTP 401.
 */
final class AuthenticationException extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid e-mail or password.');
    }

    public static function invalidToken(string $detail): self
    {
        return new self('Authentication failed: ' . $detail);
    }
}
