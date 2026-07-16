<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetOrderSummary;

use OrderHub\Application\ReadModel\OrderSummary;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

final class GetOrderSummaryHandler
{
    public function __construct(private readonly OrderSummaryReadStore $store)
    {
    }

    public function __invoke(GetOrderSummaryQuery $query): OrderSummary
    {
        // Tenant-scoped read: an order of another tenant is simply "not found".
        $summary = $this->store->findForTenant($query->tenantId, $query->orderId);
        if ($summary === null) {
            throw AggregateNotFoundException::order($query->orderId);
        }

        return $summary;
    }
}
