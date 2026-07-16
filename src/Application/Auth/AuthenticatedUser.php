<?php

declare(strict_types=1);

namespace OrderHub\Application\Auth;

use OrderHub\Application\Exceptions\AuthorizationException;

/**
 * The identity carried by a verified request: always a user, and — for
 * tenant-scoped operations — a tenant. `tenantId()` throws if the token has no
 * tenant, so scoped handlers can't silently run without one.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public string $userId,
        public ?string $tenantIdOrNull = null,
    ) {
    }

    public function hasTenant(): bool
    {
        return $this->tenantIdOrNull !== null;
    }

    public function tenantId(): string
    {
        if ($this->tenantIdOrNull === null) {
            throw new AuthorizationException('This operation requires a tenant-scoped token. Log in selecting a store.');
        }

        return $this->tenantIdOrNull;
    }
}
