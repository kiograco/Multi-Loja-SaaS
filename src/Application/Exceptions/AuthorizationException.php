<?php

declare(strict_types=1);

namespace OrderHub\Application\Exceptions;

use RuntimeException;

/**
 * Authenticated but not allowed to touch this tenant's data. Maps to HTTP 403.
 */
final class AuthorizationException extends RuntimeException
{
    public static function tenantMismatch(): self
    {
        return new self('You are not allowed to access this tenant.');
    }
}
