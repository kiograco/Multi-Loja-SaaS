<?php

declare(strict_types=1);

namespace OrderHub\Application\ReadModel;

/**
 * Port for the order_summary read model. Written by OrderSummaryProjector,
 * read by the order queries. Kept as a narrow interface so the projector and
 * query handlers never see SQL.
 */
interface OrderSummaryReadStore
{
    public function insert(OrderSummary $summary): void;

    public function updateStatus(string $orderId, string $status): void;

    public function markShipped(string $orderId, string $trackingCode): void;

    public function find(string $orderId): ?OrderSummary;

    public function findForTenant(string $tenantId, string $orderId): ?OrderSummary;

    /**
     * @return array{items: list<OrderSummary>, total: int}
     */
    public function paginateForTenant(string $tenantId, ?string $status, int $limit, int $offset): array;

    /**
     * Wipe the whole read model — used by projection rebuild.
     */
    public function truncate(): void;
}
