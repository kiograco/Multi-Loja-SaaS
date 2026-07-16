<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CancelOrder;

use OrderHub\Application\Order\OrderRepository;
use OrderHub\Application\Order\TenantGuard;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Shared\Clock;

final class CancelOrderHandler
{
    use TenantGuard;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CancelOrderCommand $command): void
    {
        $order = $this->orders->get(OrderId::fromString($command->orderId));
        $this->assertOwnedBy($order, $command->tenantId);

        $order->cancel($command->reason, $this->clock);
        $this->orders->save($order);
    }
}
