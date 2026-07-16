<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence\ReadModel;

use DateTimeImmutable;
use OrderHub\Application\ReadModel\OrderSummary;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Infrastructure\Persistence\Database;

final class PostgresOrderSummaryStore implements OrderSummaryReadStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function insert(OrderSummary $summary): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO order_summary_projection
                (order_id, tenant_id, customer_name, customer_email, status,
                 total_cents, currency, items, tracking_code, created_at, updated_at)
             VALUES
                (:order_id, :tenant_id, :customer_name, :customer_email, :status,
                 :total_cents, :currency, :items, :tracking_code, :created_at, :updated_at)
             ON CONFLICT (order_id) DO UPDATE SET
                status = EXCLUDED.status,
                updated_at = EXCLUDED.updated_at'
        );
        $stmt->execute([
            'order_id' => $summary->orderId,
            'tenant_id' => $summary->tenantId,
            'customer_name' => $summary->customerName,
            'customer_email' => $summary->customerEmail,
            'status' => $summary->status,
            'total_cents' => $summary->totalCents,
            'currency' => $summary->currency,
            'items' => json_encode($summary->items, \JSON_THROW_ON_ERROR),
            'tracking_code' => $summary->trackingCode,
            'created_at' => $summary->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $summary->updatedAt->format(DateTimeImmutable::ATOM),
        ]);
    }

    public function updateStatus(string $orderId, string $status): void
    {
        $stmt = $this->database->pdo()->prepare(
            'UPDATE order_summary_projection SET status = :status, updated_at = now() WHERE order_id = :id'
        );
        $stmt->execute(['status' => $status, 'id' => $orderId]);
    }

    public function markShipped(string $orderId, string $trackingCode): void
    {
        $stmt = $this->database->pdo()->prepare(
            'UPDATE order_summary_projection
             SET status = :status, tracking_code = :tracking, updated_at = now()
             WHERE order_id = :id'
        );
        $stmt->execute(['status' => 'enviado', 'tracking' => $trackingCode, 'id' => $orderId]);
    }

    public function find(string $orderId): ?OrderSummary
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT * FROM order_summary_projection WHERE order_id = :id'
        );
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findForTenant(string $tenantId, string $orderId): ?OrderSummary
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT * FROM order_summary_projection WHERE order_id = :id AND tenant_id = :tenant'
        );
        $stmt->execute(['id' => $orderId, 'tenant' => $tenantId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function paginateForTenant(string $tenantId, ?string $status, int $limit, int $offset): array
    {
        $where = 'tenant_id = :tenant';
        $params = ['tenant' => $tenantId];
        if ($status !== null) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }

        $countStmt = $this->database->pdo()->prepare(
            "SELECT COUNT(*) FROM order_summary_projection WHERE {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $listStmt = $this->database->pdo()->prepare(
            "SELECT * FROM order_summary_projection WHERE {$where}
             ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $listStmt->bindValue(':' . $key, $value);
        }
        $listStmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $listStmt->execute();

        $items = [];
        /** @var array<string, mixed> $row */
        foreach ($listStmt as $row) {
            $items[] = $this->hydrate($row);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function truncate(): void
    {
        $this->database->pdo()->exec('TRUNCATE order_summary_projection');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OrderSummary
    {
        /** @var list<array{productId: string, productName: string, unitPriceCents: int, currency: string, quantity: int}> $items */
        $items = json_decode((string) $row['items'], true, 512, \JSON_THROW_ON_ERROR);

        return new OrderSummary(
            (string) $row['order_id'],
            (string) $row['tenant_id'],
            (string) $row['customer_name'],
            (string) $row['customer_email'],
            (string) $row['status'],
            (int) $row['total_cents'],
            (string) $row['currency'],
            $items,
            $row['tracking_code'] !== null ? (string) $row['tracking_code'] : null,
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
