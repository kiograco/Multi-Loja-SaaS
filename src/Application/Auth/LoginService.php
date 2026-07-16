<?php

declare(strict_types=1);

namespace OrderHub\Application\Auth;

use OrderHub\Application\Exceptions\AuthenticationException;
use OrderHub\Domain\Shared\Clock;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;
use OrderHub\Domain\User\UserRepository;

/**
 * Authenticates a user and issues a JWT carrying user_id and (when resolvable)
 * tenant_id.
 *
 * A user may own several stores, but a token is scoped to one. The active tenant
 * is chosen as: the requested tenant (if owned), else the user's single tenant,
 * else none — in which case only non-scoped endpoints (like POST /tenants) work.
 */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TenantRepository $tenants,
        private readonly TokenIssuer $tokens,
        private readonly Clock $clock,
        private readonly int $ttlSeconds,
    ) {
    }

    /**
     * @return array{token: string, userId: string, tenantId: ?string}
     */
    public function login(string $email, string $plainPassword, ?string $requestedTenantId = null): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->verifyPassword($plainPassword)) {
            throw AuthenticationException::invalidCredentials();
        }

        $tenantId = $this->resolveTenant($user->id->value, $requestedTenantId);

        $issuedAt = $this->clock->now()->getTimestamp();
        $claims = [
            'sub' => $user->id->value,
            'user_id' => $user->id->value,
            'tenant_id' => $tenantId,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->ttlSeconds,
        ];

        return [
            'token' => $this->tokens->issue($claims),
            'userId' => $user->id->value,
            'tenantId' => $tenantId,
        ];
    }

    private function resolveTenant(string $userId, ?string $requestedTenantId): ?string
    {
        $owned = $this->tenants->findByOwner($userId);
        if ($owned === []) {
            return null;
        }

        if ($requestedTenantId !== null) {
            foreach ($owned as $tenant) {
                if ($tenant->id->equals(TenantId::fromString($requestedTenantId))) {
                    return $tenant->id->value;
                }
            }
            throw AuthenticationException::invalidToken('requested tenant is not owned by this user');
        }

        // Default to the user's first store when they didn't pick one.
        return $owned[0]->id->value;
    }
}
