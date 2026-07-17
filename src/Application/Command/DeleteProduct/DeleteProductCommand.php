<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\DeleteProduct;

use OrderHub\Application\Bus\Command;

final readonly class DeleteProductCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $productId,
    ) {
    }
}
