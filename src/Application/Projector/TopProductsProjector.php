<?php

declare(strict_types=1);

namespace OrderHub\Application\Projector;

use OrderHub\Application\EventBus\EventSubscriber;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Application\ReadModel\TopProductsReadStore;
use OrderHub\Domain\Order\Events\PaymentReceived;
use OrderHub\Domain\Shared\DomainEvent;

/**
 * Aggregates units sold and revenue per product per tenant, again keyed off the
 * paid order. Reads the order summary for the line items, then accumulates each.
 */
final class TopProductsProjector implements EventSubscriber
{
    public function __construct(
        private readonly TopProductsReadStore $store,
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

        foreach ($summary->items as $item) {
            $this->store->recordSale(
                $summary->tenantId,
                $item['productId'],
                $item['productName'],
                $item['quantity'],
                $item['unitPriceCents'] * $item['quantity'],
            );
        }
    }
}
