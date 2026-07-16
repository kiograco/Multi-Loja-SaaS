<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListOrders;

use OrderHub\Application\Bus\Query;

final readonly class ListOrdersQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
