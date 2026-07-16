<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Middleware;

use OrderHub\Application\Auth\AuthenticatedUser;
use OrderHub\Application\Auth\TokenIssuer;
use OrderHub\Application\Exceptions\AuthenticationException;
use OrderHub\Interface\Api\Http\Request;

/**
 * Turns a `Authorization: Bearer <jwt>` header into an AuthenticatedUser by
 * verifying the token and reading its user_id / tenant_id claims. The tenant id
 * is trusted only from the token — never from the request body — which is the
 * backbone of the multi-tenancy isolation guarantee.
 */
final class Authenticator
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function authenticate(Request $request): AuthenticatedUser
    {
        $header = $request->header('authorization');
        if ($header === null || !preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            throw AuthenticationException::invalidToken('missing or malformed Authorization header');
        }

        $claims = $this->tokens->verify($m[1]);

        $userId = isset($claims['user_id']) ? (string) $claims['user_id'] : null;
        if ($userId === null || $userId === '') {
            throw AuthenticationException::invalidToken('token has no user_id');
        }

        $tenantId = isset($claims['tenant_id']) && $claims['tenant_id'] !== null && $claims['tenant_id'] !== ''
            ? (string) $claims['tenant_id']
            : null;

        return new AuthenticatedUser($userId, $tenantId);
    }
}
