<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListProducts;

use OrderHub\Application\Bus\Query;

final readonly class ListProductsQuery implements Query
{
    public function __construct(public string $tenantId)
    {
    }
}
