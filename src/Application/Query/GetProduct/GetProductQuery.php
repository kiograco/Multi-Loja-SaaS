<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetProduct;

use OrderHub\Application\Bus\Query;

final readonly class GetProductQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public string $productId,
    ) {
    }
}
