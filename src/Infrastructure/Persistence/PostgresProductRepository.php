<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Money;

/**
 * Postgres adapter for products. Every read and write carries a tenant_id in
 * the WHERE/INSERT, so the SQL itself enforces isolation between stores — a
 * product row is only ever reachable through its owning tenant.
 */
final class PostgresProductRepository implements ProductRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function save(Product $product): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO products (id, tenant_id, name, price_cents, currency, stock_quantity, updated_at)
             VALUES (:id, :tenant_id, :name, :price_cents, :currency, :stock_quantity, now())
             ON CONFLICT (id) DO UPDATE SET
                name = EXCLUDED.name,
                price_cents = EXCLUDED.price_cents,
                currency = EXCLUDED.currency,
                stock_quantity = EXCLUDED.stock_quantity,
                updated_at = now()
             WHERE products.tenant_id = EXCLUDED.tenant_id'
        );
        $stmt->execute([
            'id' => $product->id->value,
            'tenant_id' => $product->tenantId,
            'name' => $product->name(),
            'price_cents' => $product->price()->cents,
            'currency' => $product->price()->currency,
            'stock_quantity' => $product->stockQuantity(),
        ]);
    }

    public function findById(string $tenantId, ProductId $id): ?Product
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT * FROM products WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id->value, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllForTenant(string $tenantId): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT * FROM products WHERE tenant_id = :tenant_id ORDER BY name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $products = [];
        /** @var array{id: string, tenant_id: string, name: string, price_cents: int, currency: string, stock_quantity: int} $row */
        foreach ($stmt as $row) {
            $products[] = $this->hydrate($row);
        }

        return $products;
    }

    public function searchForTenant(string $tenantId, ?string $search, int $perPage, int $offset): array
    {
        $where = 'tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($search !== null && $search !== '') {
            $where .= ' AND name ILIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $countStmt = $this->database->pdo()->prepare("SELECT COUNT(*) FROM products WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->database->pdo()->prepare(
            "SELECT * FROM products WHERE {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        /** @var array{id: string, tenant_id: string, name: string, price_cents: int, currency: string, stock_quantity: int} $row */
        foreach ($stmt as $row) {
            $items[] = $this->hydrate($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array{id: string, tenant_id: string, name: string, price_cents: int, currency: string, stock_quantity: int} $row
     */
    private function hydrate(array $row): Product
    {
        return new Product(
            ProductId::fromString($row['id']),
            $row['tenant_id'],
            $row['name'],
            Money::ofCents((int) $row['price_cents'], $row['currency']),
            (int) $row['stock_quantity'],
        );
    }
}
