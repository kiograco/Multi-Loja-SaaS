<?php

declare(strict_types=1);

namespace OrderHub\Tests\Support;

use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Domain\Shared\Clock;
use OrderHub\Domain\Shared\Money;
use Ramsey\Uuid\Uuid;

/**
 * Builds valid Orders for tests, so each test only spells out what it cares about.
 */
final class OrderFactory
{
    public static function created(?Clock $clock = null, ?string $tenantId = null): Order
    {
        return Order::create(
            OrderId::generate(),
            $tenantId ?? Uuid::uuid4()->toString(),
            'Ada Lovelace',
            'ada@example.com',
            [
                new OrderItem(Uuid::uuid4()->toString(), 'Analytical Engine', Money::ofCents(150000), 1),
                new OrderItem(Uuid::uuid4()->toString(), 'Punch Card Pack', Money::ofCents(2500), 4),
            ],
            $clock ?? new FrozenClock(),
        );
    }
}
