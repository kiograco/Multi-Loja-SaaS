<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetOrderInvoice;

use OrderHub\Application\Bus\Query;

final readonly class GetOrderInvoiceQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
    ) {
    }
}
