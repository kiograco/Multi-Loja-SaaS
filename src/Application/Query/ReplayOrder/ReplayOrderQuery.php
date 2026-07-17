<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ReplayOrder;

use OrderHub\Application\Bus\Query;

final readonly class ReplayOrderQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
    ) {
    }
}
