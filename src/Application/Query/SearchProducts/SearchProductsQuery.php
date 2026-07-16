<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\SearchProducts;

use OrderHub\Application\Bus\Query;

final readonly class SearchProductsQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
