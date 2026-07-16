<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetOrderEventTimeline;

use OrderHub\Application\Bus\Query;

final readonly class GetOrderEventTimelineQuery implements Query
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
    ) {
    }
}
