<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CreateOrder;

use OrderHub\Application\Bus\Command;

final readonly class CreateOrderCommand implements Command
{
    /**
     * @param list<array{productId: string, quantity: int}> $items
     */
    public function __construct(
        public string $tenantId,
        public string $customerName,
        public string $customerEmail,
        public array $items,
    ) {
    }
}
