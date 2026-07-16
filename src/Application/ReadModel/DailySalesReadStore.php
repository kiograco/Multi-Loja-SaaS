<?php

declare(strict_types=1);

namespace OrderHub\Application\ReadModel;

interface DailySalesReadStore
{
    /**
     * Record one paid order for a tenant on a given day (YYYY-MM-DD),
     * incrementing both the order count and the revenue.
     */
    public function recordSale(string $tenantId, string $date, int $revenueCents): void;

    /**
     * @return list<array{date: string, ordersCount: int, revenueCents: int}>
     */
    public function seriesForTenant(string $tenantId): array;

    public function truncate(): void;
}
