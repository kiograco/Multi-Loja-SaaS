<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CreateOrder;

use OrderHub\Application\Order\OrderRepository;
use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Clock;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Turns a "create order" request into a new Order aggregate. Line prices and
 * names are snapshotted from the current products (tenant-scoped lookups), so
 * the order keeps its historical price even if the product changes later.
 * Stock is NOT decremented here — that is a side effect of payment.
 */
final class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateOrderCommand $command): string
    {
        $items = [];
        foreach ($command->items as $line) {
            $product = $this->products->findById($command->tenantId, ProductId::fromString($line['productId']));
            if ($product === null) {
                throw AggregateNotFoundException::product($line['productId']);
            }
            $items[] = new OrderItem(
                $product->id->value,
                $product->name(),
                $product->price(),
                $line['quantity'],
            );
        }

        $order = Order::create(
            OrderId::generate(),
            $command->tenantId,
            $command->customerName,
            $command->customerEmail,
            $items,
            $this->clock,
        );
        $this->orders->save($order);

        return $order->id()->value;
    }
}
