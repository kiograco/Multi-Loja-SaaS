<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListProducts;

use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductRepository;

final class ListProductsHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(ListProductsQuery $query): array
    {
        return array_map(
            static fn (Product $p): array => [
                'id' => $p->id->value,
                'name' => $p->name(),
                'price' => $p->price()->toDecimal(),
                'priceCents' => $p->price()->cents,
                'currency' => $p->price()->currency,
                'stockQuantity' => $p->stockQuantity(),
            ],
            $this->products->findAllForTenant($query->tenantId),
        );
    }
}
