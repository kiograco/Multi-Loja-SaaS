<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\UpdateProduct;

use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Shared\Money;

final class UpdateProductHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function __invoke(UpdateProductCommand $command): void
    {
        // Tenant-scoped lookup: an update can never reach another tenant's product.
        $product = $this->products->findById($command->tenantId, ProductId::fromString($command->productId));
        if ($product === null) {
            throw AggregateNotFoundException::product($command->productId);
        }

        if ($command->name !== null) {
            $product->rename($command->name);
        }
        if ($command->priceCents !== null) {
            $product->changePrice(Money::ofCents($command->priceCents, $command->currency));
        }
        if ($command->stockQuantity !== null) {
            $product->changeStock($command->stockQuantity);
        }

        $this->products->save($product);
    }
}
