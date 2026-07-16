<?php

declare(strict_types=1);

namespace OrderHub\Application\Projector;

use OrderHub\Application\EventBus\EventSubscriber;
use OrderHub\Application\ReadModel\OrderSummary;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Domain\Order\Events\OrderCancelled;
use OrderHub\Domain\Order\Events\OrderCreated;
use OrderHub\Domain\Order\Events\OrderDelivered;
use OrderHub\Domain\Order\Events\OrderShipped;
use OrderHub\Domain\Order\Events\PaymentReceived;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Shared\DomainEvent;

/**
 * Keeps `order_summary_projection` in sync with the Order event stream. Runs
 * synchronously right after events are committed, so a read immediately after a
 * write always sees the new state. This is the first projector registered, so
 * downstream aggregating projectors can rely on the summary row already existing.
 */
final class OrderSummaryProjector implements EventSubscriber
{
    public function __construct(private readonly OrderSummaryReadStore $store)
    {
    }

    public function on(DomainEvent $event): void
    {
        match (true) {
            $event instanceof OrderCreated => $this->onCreated($event),
            $event instanceof PaymentReceived => $this->store->updateStatus($event->orderId, OrderStatus::Paid->value),
            $event instanceof OrderShipped => $this->store->markShipped($event->orderId, $event->trackingCode),
            $event instanceof OrderDelivered => $this->store->updateStatus($event->orderId, OrderStatus::Delivered->value),
            $event instanceof OrderCancelled => $this->store->updateStatus($event->orderId, OrderStatus::Cancelled->value),
            default => null,
        };
    }

    private function onCreated(OrderCreated $event): void
    {
        $this->store->insert(new OrderSummary(
            $event->orderId,
            $event->tenantId,
            $event->customerName,
            $event->customerEmail,
            OrderStatus::Created->value,
            $event->totalAmount->cents,
            $event->totalAmount->currency,
            array_map(static fn (OrderItem $i): array => $i->toArray(), $event->items),
            null,
            $event->occurredAt,
            $event->occurredAt,
        ));
    }
}
