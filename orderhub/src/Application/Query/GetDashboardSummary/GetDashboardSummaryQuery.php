<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetDashboardSummary;

use OrderHub\Application\Bus\Query;

final readonly class GetDashboardSummaryQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public int $topProductsLimit = 5,
    ) {
    }
}
