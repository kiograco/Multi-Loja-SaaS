<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\DeliverOrder;

use OrderHub\Application\Order\OrderRepository;
use OrderHub\Application\Order\TenantGuard;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Shared\Clock;

final class DeliverOrderHandler
{
    use TenantGuard;

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeliverOrderCommand $command): void
    {
        $order = $this->orders->get(OrderId::fromString($command->orderId));
        $this->assertOwnedBy($order, $command->tenantId);

        $order->deliver($this->clock);
        $this->orders->save($order);
    }
}
