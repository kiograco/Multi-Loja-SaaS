<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetProduct;

use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Looks up a single product directly via ProductRepository::findById() —
 * previously the Web edit form had to scan the entire (unpaginated)
 * ListProductsQuery result to find one product by id.
 */
final class GetProductHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @return array{id: string, name: string, price: string, priceCents: int, currency: string, stockQuantity: int}
     */
    public function __invoke(GetProductQuery $query): array
    {
        $product = $this->products->findById($query->tenantId, ProductId::fromString($query->productId));
        if ($product === null) {
            throw AggregateNotFoundException::product($query->productId);
        }

        return [
            'id' => $product->id->value,
            'name' => $product->name(),
            'price' => $product->price()->toDecimal(),
            'priceCents' => $product->price()->cents,
            'currency' => $product->price()->currency,
            'stockQuantity' => $product->stockQuantity(),
        ];
    }
}
