<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\SearchProducts;

use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductRepository;

/**
 * Paginated, name-searchable product listing — ListProductsQuery loaded every
 * product for a tenant unbounded, which becomes a usability and performance
 * problem once a store passes a few dozen products (Seção 18).
 */
final class SearchProductsHandler
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function __invoke(SearchProductsQuery $query): array
    {
        $page = max(1, $query->page);
        $perPage = min(self::MAX_PER_PAGE, max(1, $query->perPage));
        $offset = ($page - 1) * $perPage;

        $result = $this->products->searchForTenant($query->tenantId, $query->search, $perPage, $offset);
        $total = $result['total'];

        return [
            'data' => array_map(static fn (Product $p): array => [
                'id' => $p->id->value,
                'name' => $p->name(),
                'price' => $p->price()->toDecimal(),
                'priceCents' => $p->price()->cents,
                'currency' => $p->price()->currency,
                'stockQuantity' => $p->stockQuantity(),
            ], $result['items']),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }
}
