<?php

declare(strict_types=1);

namespace OrderHub\Application\ReadModel;

interface TopProductsReadStore
{
    public function recordSale(string $tenantId, string $productId, string $productName, int $units, int $revenueCents): void;

    /**
     * @return list<array{productId: string, productName: string, unitsSold: int, revenueCents: int}>
     */
    public function topForTenant(string $tenantId, int $limit): array;

    public function truncate(): void;
}
