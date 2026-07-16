<?php

declare(strict_types=1);

namespace OrderHub\Tests\Support;

use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;

final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> keyed by "tenantId:productId" */
    private array $products = [];

    public function save(Product $product): void
    {
        $this->products[$product->tenantId . ':' . $product->id->value] = $product;
    }

    public function findById(string $tenantId, ProductId $id): ?Product
    {
        return $this->products[$tenantId . ':' . $id->value] ?? null;
    }

    public function findAllForTenant(string $tenantId): array
    {
        $result = [];
        foreach ($this->products as $product) {
            if ($product->tenantId === $tenantId) {
                $result[] = $product;
            }
        }

        return $result;
    }
}
