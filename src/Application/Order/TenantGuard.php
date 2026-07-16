<?php

declare(strict_types=1);

namespace OrderHub\Application\Order;

use OrderHub\Application\Exceptions\AuthorizationException;
use OrderHub\Domain\Order\Order;

/**
 * Orders are loaded from the event store by id alone, so every write-side
 * handler must confirm the order belongs to the authenticated tenant before
 * acting. Centralised here so the check can't be forgotten inconsistently.
 */
trait TenantGuard
{
    private function assertOwnedBy(Order $order, string $tenantId): void
    {
        if ($order->tenantId() !== $tenantId) {
            throw AuthorizationException::tenantMismatch();
        }
    }
}
