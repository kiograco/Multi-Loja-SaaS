<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListOrders;

use OrderHub\Application\ReadModel\OrderSummary;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;

final class ListOrdersHandler
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly OrderSummaryReadStore $store)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function __invoke(ListOrdersQuery $query): array
    {
        $page = max(1, $query->page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $query->perPage));
        $offset = ($page - 1) * $perPage;

        $result = $this->store->paginateForTenant($query->tenantId, $query->status, $perPage, $offset);
        $total = $result['total'];

        return [
            'data' => array_map(static fn (OrderSummary $s): array => $s->toArray(), $result['items']),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }
}
