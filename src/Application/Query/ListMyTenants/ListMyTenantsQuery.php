<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListMyTenants;

use OrderHub\Application\Bus\Query;

final readonly class ListMyTenantsQuery implements Query
{
    public function __construct(public string $userId)
    {
    }
}
