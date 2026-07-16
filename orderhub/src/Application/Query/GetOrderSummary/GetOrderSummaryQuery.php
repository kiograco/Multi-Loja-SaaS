<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetOrderSummary;

use OrderHub\Application\Bus\Query;

final readonly class GetOrderSummaryQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
    ) {
    }
}
