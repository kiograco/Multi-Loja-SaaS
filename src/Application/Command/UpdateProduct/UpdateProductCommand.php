<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\UpdateProduct;

use OrderHub\Application\Bus\Command;

/**
 * Partial update: only non-null fields are applied (PATCH semantics).
 */
final readonly class UpdateProductCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $productId,
        public ?string $name = null,
        public ?int $priceCents = null,
        public ?int $stockQuantity = null,
        public string $currency = 'BRL',
    ) {
    }
}
