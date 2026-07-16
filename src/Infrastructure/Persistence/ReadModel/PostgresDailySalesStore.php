<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence\ReadModel;

use OrderHub\Application\ReadModel\DailySalesReadStore;
use OrderHub\Infrastructure\Persistence\Database;

final class PostgresDailySalesStore implements DailySalesReadStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function recordSale(string $tenantId, string $date, int $revenueCents): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO daily_sales_projection (tenant_id, sales_date, orders_count, revenue_cents)
             VALUES (:tenant, :date, 1, :revenue)
             ON CONFLICT (tenant_id, sales_date) DO UPDATE SET
                orders_count = daily_sales_projection.orders_count + 1,
                revenue_cents = daily_sales_projection.revenue_cents + EXCLUDED.revenue_cents'
        );
        $stmt->execute(['tenant' => $tenantId, 'date' => $date, 'revenue' => $revenueCents]);
    }

    public function seriesForTenant(string $tenantId): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT sales_date, orders_count, revenue_cents
             FROM daily_sales_projection WHERE tenant_id = :tenant ORDER BY sales_date ASC'
        );
        $stmt->execute(['tenant' => $tenantId]);

        $series = [];
        /** @var array{sales_date: string, orders_count: int, revenue_cents: int} $row */
        foreach ($stmt as $row) {
            $series[] = [
                'date' => (string) $row['sales_date'],
                'ordersCount' => (int) $row['orders_count'],
                'revenueCents' => (int) $row['revenue_cents'],
            ];
        }

        return $series;
    }

    public function truncate(): void
    {
        $this->database->pdo()->exec('TRUNCATE daily_sales_projection');
    }
}
