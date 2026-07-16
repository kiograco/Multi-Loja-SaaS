<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence\ReadModel;

use OrderHub\Application\ReadModel\TopProductsReadStore;
use OrderHub\Infrastructure\Persistence\Database;

final class PostgresTopProductsStore implements TopProductsReadStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function recordSale(string $tenantId, string $productId, string $productName, int $units, int $revenueCents): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO top_products_projection (tenant_id, product_id, product_name, units_sold, revenue_cents)
             VALUES (:tenant, :product, :name, :units, :revenue)
             ON CONFLICT (tenant_id, product_id) DO UPDATE SET
                product_name = EXCLUDED.product_name,
                units_sold = top_products_projection.units_sold + EXCLUDED.units_sold,
                revenue_cents = top_products_projection.revenue_cents + EXCLUDED.revenue_cents'
        );
        $stmt->execute([
            'tenant' => $tenantId,
            'product' => $productId,
            'name' => $productName,
            'units' => $units,
            'revenue' => $revenueCents,
        ]);
    }

    public function topForTenant(string $tenantId, int $limit): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT product_id, product_name, units_sold, revenue_cents
             FROM top_products_projection WHERE tenant_id = :tenant
             ORDER BY units_sold DESC, revenue_cents DESC LIMIT :limit'
        );
        $stmt->bindValue(':tenant', $tenantId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        /** @var array{product_id: string, product_name: string, units_sold: int, revenue_cents: int} $row */
        foreach ($stmt as $row) {
            $rows[] = [
                'productId' => (string) $row['product_id'],
                'productName' => (string) $row['product_name'],
                'unitsSold' => (int) $row['units_sold'],
                'revenueCents' => (int) $row['revenue_cents'],
            ];
        }

        return $rows;
    }

    public function truncate(): void
    {
        $this->database->pdo()->exec('TRUNCATE top_products_projection');
    }
}
