<?php

declare(strict_types=1);

namespace OrderHub\Application\Projector;

use OrderHub\Application\EventBus\EventSubscriber;
use OrderHub\Application\ReadModel\DailySalesReadStore;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Domain\Order\Events\PaymentReceived;
use OrderHub\Domain\Shared\DomainEvent;

/**
 * Aggregates realised revenue per tenant per day. A "sale" is a paid order, so
 * this reacts to PaymentReceived. That event only carries the order id, so we
 * read the (already-projected) order summary to recover the tenant and amount —
 * a read-model lookup, never the event store.
 */
final class DailySalesProjector implements EventSubscriber
{
    public function __construct(
        private readonly DailySalesReadStore $store,
        private readonly OrderSummaryReadStore $orders,
    ) {
    }

    public function on(DomainEvent $event): void
    {
        if (!$event instanceof PaymentReceived) {
            return;
        }

        $summary = $this->orders->find($event->orderId);
        if ($summary === null) {
            return;
        }

        $date = $event->occurredAt->format('Y-m-d');
        $this->store->recordSale($summary->tenantId, $date, $event->amountPaid->cents);
    }
}
