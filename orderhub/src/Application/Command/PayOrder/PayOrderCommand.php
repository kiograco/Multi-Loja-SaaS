<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\PayOrder;

use OrderHub\Application\Bus\Command;

final readonly class PayOrderCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $orderId,
        public string $paymentMethod,
    ) {
    }
}
