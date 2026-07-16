<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Middleware;

use OrderHub\Application\Auth\AuthenticatedUser;
use OrderHub\Interface\Web\Http\Session;

/**
 * Guards every `/app/*` route except `/app/login`. Reads identity from the PHP
 * session (set at login) rather than a bearer token — the Web channel's own
 * authentication mechanism, deliberately distinct from the API's JWT.
 */
final class RequireWebAuthMiddleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function authenticate(): ?AuthenticatedUser
    {
        $userId = $this->session->get('user_id');
        if (!\is_string($userId) || $userId === '') {
            return null;
        }

        $tenantId = $this->session->get('tenant_id');

        return new AuthenticatedUser($userId, \is_string($tenantId) && $tenantId !== '' ? $tenantId : null);
    }
}
