<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CreateProduct;

use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Money;

final class CreateProductHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function __invoke(CreateProductCommand $command): string
    {
        $product = new Product(
            ProductId::generate(),
            $command->tenantId,
            $command->name,
            Money::ofCents($command->priceCents, $command->currency),
            $command->stockQuantity,
        );
        $this->products->save($product);

        return $product->id->value;
    }
}
