<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use DateTimeImmutable;
use OrderHub\Application\Webhook\WebhookDeliveryRepository;

final class PostgresWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function record(
        string $tenantId,
        ?string $orderId,
        string $url,
        bool $success,
        ?int $responseCode,
        ?string $error,
        DateTimeImmutable $attemptedAt,
    ): void {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO webhook_delivery_attempts (tenant_id, order_id, url, success, response_code, error, attempted_at)
             VALUES (:tenant_id, :order_id, :url, :success, :response_code, :error, :attempted_at)'
        );
        // Bound explicitly (not via execute(array)): PDO's array form stringifies
        // `false` to '', which Postgres rejects for a boolean column.
        $stmt->bindValue('tenant_id', $tenantId);
        $stmt->bindValue('order_id', $orderId);
        $stmt->bindValue('url', $url);
        $stmt->bindValue('success', $success, \PDO::PARAM_BOOL);
        $stmt->bindValue('response_code', $responseCode, $responseCode === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue('error', $error);
        $stmt->bindValue('attempted_at', $attemptedAt->format(DateTimeImmutable::ATOM));
        $stmt->execute();
    }

    public function listForTenant(string $tenantId, int $limit = 20): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT order_id, url, success, response_code, error, attempted_at
             FROM webhook_delivery_attempts
             WHERE tenant_id = :tenant_id
             ORDER BY attempted_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('tenant_id', $tenantId);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $attempts = [];
        /** @var array{order_id: ?string, url: string, success: bool, response_code: ?int, error: ?string, attempted_at: string} $row */
        foreach ($stmt as $row) {
            $attempts[] = [
                'orderId' => $row['order_id'],
                'url' => $row['url'],
                'success' => (bool) $row['success'],
                'responseCode' => $row['response_code'] !== null ? (int) $row['response_code'] : null,
                'error' => $row['error'],
                'attemptedAt' => $row['attempted_at'],
            ];
        }

        return $attempts;
    }
}
