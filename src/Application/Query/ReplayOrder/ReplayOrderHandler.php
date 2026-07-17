<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ReplayOrder;

use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Domain\Shared\EventStoreInterface;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Web-safe wrapper around what `bin/console order:replay` already does:
 * reconstruct an order purely from the event store, bypassing every
 * projection, to prove the read model is fully derived. EventStoreInterface::load()
 * has no tenant concept (it's a raw aggregate stream), so the tenant check
 * happens here, after reconstitution — the same isolation guarantee every
 * other tenant-scoped query gets, just enforced one step later.
 */
final class ReplayOrderHandler
{
    public function __construct(private readonly EventStoreInterface $eventStore)
    {
    }

    /**
     * @return array{
     *     orderId: string,
     *     status: string,
     *     version: int,
     *     customerName: string,
     *     customerEmail: string,
     *     total: string,
     *     currency: string,
     *     items: list<array{productName: string, quantity: int, unitPrice: string, currency: string}>,
     *     events: list<array{type: string, occurredAt: string}>,
     * }
     */
    public function __invoke(ReplayOrderQuery $query): array
    {
        $stream = $this->eventStore->load($query->orderId);
        if ($stream->isEmpty()) {
            throw AggregateNotFoundException::order($query->orderId);
        }

        $order = Order::reconstituteFrom($stream);
        if ($order->tenantId() !== $query->tenantId) {
            // Tenant-scoped read: an order of another tenant is simply "not found".
            throw AggregateNotFoundException::order($query->orderId);
        }

        return [
            'orderId' => $order->id()->value,
            'status' => $order->status()->value,
            'version' => $order->version(),
            'customerName' => $order->customerName(),
            'customerEmail' => $order->customerEmail(),
            'total' => $order->totalAmount()->toDecimal(),
            'currency' => $order->totalAmount()->currency,
            'items' => array_map(static fn (OrderItem $item): array => [
                'productName' => $item->productName,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unitPrice->toDecimal(),
                'currency' => $item->unitPrice->currency,
            ], $order->items()),
            'events' => array_map(static fn ($event): array => [
                'type' => $event->eventType(),
                'occurredAt' => $event->occurredAt()->format(\DateTimeImmutable::ATOM),
            ], $stream->toArray()),
        ];
    }
}
