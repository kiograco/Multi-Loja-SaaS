<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CancelOrder;

use OrderHub\Application\Bus\Command;

final readonly class CancelOrderCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
        public string $reason,
    ) {
    }
}
