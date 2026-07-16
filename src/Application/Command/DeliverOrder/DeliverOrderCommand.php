<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\DeliverOrder;

use OrderHub\Application\Bus\Command;

final readonly class DeliverOrderCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
    ) {
    }
}
