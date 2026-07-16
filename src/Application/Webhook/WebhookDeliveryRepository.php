<?php

declare(strict_types=1);

namespace OrderHub\Application\Webhook;

use DateTimeImmutable;

/**
 * Port for the queryable log of DispatchWebhookJob attempts (Seção 6/18):
 * before this, a failure only ever reached the server log, invisible to the
 * store owner.
 */
interface WebhookDeliveryRepository
{
    public function record(
        string $tenantId,
        ?string $orderId,
        string $url,
        bool $success,
        ?int $responseCode,
        ?string $error,
        DateTimeImmutable $attemptedAt,
    ): void;

    /**
     * @return list<array{orderId: ?string, url: string, success: bool, responseCode: ?int, error: ?string, attemptedAt: string}>
     */
    public function listForTenant(string $tenantId, int $limit = 20): array;
}
