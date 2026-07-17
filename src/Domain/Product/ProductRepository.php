<?php

declare(strict_types=1);

namespace OrderHub\Domain\Product;

/**
 * Port for product persistence. Every method is tenant-scoped: the tenant id
 * comes from the authenticated context, never from client input, so one store
 * can never reach another store's products.
 */
interface ProductRepository
{
    public function save(Product $product): void;

    public function delete(string $tenantId, ProductId $id): void;

    public function findById(string $tenantId, ProductId $id): ?Product;

    /**
     * @return list<Product>
     */
    public function findAllForTenant(string $tenantId): array;

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function searchForTenant(string $tenantId, ?string $search, int $perPage, int $offset): array;
}
