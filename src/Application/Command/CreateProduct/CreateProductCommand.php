<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CreateProduct;

use OrderHub\Application\Bus\Command;

final readonly class CreateProductCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $name,
        public int $priceCents,
        public int $stockQuantity,
        public string $currency = 'BRL',
    ) {
    }
}
