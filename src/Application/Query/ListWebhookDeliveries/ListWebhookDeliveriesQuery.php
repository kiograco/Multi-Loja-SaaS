<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListWebhookDeliveries;

use OrderHub\Application\Bus\Query;

final readonly class ListWebhookDeliveriesQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public int $limit = 20,
    ) {
    }
}
