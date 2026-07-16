<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Auth\AuthenticatedUser;
use OrderHub\Interface\Api\Http\Request;

/**
 * Rebuilds the AuthenticatedUser the kernel stashed in the request attributes,
 * so controllers get a typed identity instead of poking at raw attributes.
 */
trait CurrentUser
{
    private function currentUser(Request $request): AuthenticatedUser
    {
        $userId = (string) $request->attribute('user_id', '');
        $tenantId = $request->attribute('tenant_id');

        return new AuthenticatedUser($userId, $tenantId !== '' ? $tenantId : null);
    }
}
