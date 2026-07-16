<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Auth\AuthenticatedUser;
use OrderHub\Interface\Web\Http\WebRequest;

/**
 * Rebuilds the AuthenticatedUser the Web Kernel stashed in the request
 * attributes after checking the session — mirrors Api\Controller\CurrentUser
 * so controllers on both channels read identity the same way.
 */
trait CurrentUser
{
    private function currentUser(WebRequest $request): AuthenticatedUser
    {
        $userId = (string) $request->attribute('user_id', '');
        $tenantId = $request->attribute('tenant_id');

        return new AuthenticatedUser($userId, $tenantId !== '' ? $tenantId : null);
    }
}
