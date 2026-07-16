<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\ShipOrder;

use OrderHub\Application\Bus\Command;

final readonly class ShipOrderCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
        public string $trackingCode,
    ) {
    }
}
