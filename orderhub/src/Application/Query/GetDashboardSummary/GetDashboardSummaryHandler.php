<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetDashboardSummary;

use OrderHub\Application\ReadModel\DailySalesReadStore;
use OrderHub\Application\ReadModel\TopProductsReadStore;

/**
 * Assembles the dashboard entirely from pre-aggregated projections. It never
 * touches the event store or recomputes from raw events, which is what keeps it
 * fast regardless of how many orders exist (see Fase 8 load test).
 */
final class GetDashboardSummaryHandler
{
    public function __construct(
        private readonly DailySalesReadStore $dailySales,
        private readonly TopProductsReadStore $topProducts,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(GetDashboardSummaryQuery $query): array
    {
        $series = $this->dailySales->seriesForTenant($query->tenantId);

        $totalOrders = 0;
        $totalRevenueCents = 0;
        foreach ($series as $day) {
            $totalOrders += $day['ordersCount'];
            $totalRevenueCents += $day['revenueCents'];
        }

        $averageTicketCents = $totalOrders > 0 ? intdiv($totalRevenueCents, $totalOrders) : 0;

        return [
            'totals' => [
                'paidOrders' => $totalOrders,
                'revenue' => number_format($totalRevenueCents / 100, 2, '.', ''),
                'revenueCents' => $totalRevenueCents,
                'averageTicket' => number_format($averageTicketCents / 100, 2, '.', ''),
                'averageTicketCents' => $averageTicketCents,
            ],
            'salesByDay' => array_map(static fn (array $d): array => [
                'date' => $d['date'],
                'ordersCount' => $d['ordersCount'],
                'revenue' => number_format($d['revenueCents'] / 100, 2, '.', ''),
                'revenueCents' => $d['revenueCents'],
            ], $series),
            'topProducts' => array_map(static fn (array $p): array => [
                'productId' => $p['productId'],
                'productName' => $p['productName'],
                'unitsSold' => $p['unitsSold'],
                'revenue' => number_format($p['revenueCents'] / 100, 2, '.', ''),
                'revenueCents' => $p['revenueCents'],
            ], $this->topProducts->topForTenant($query->tenantId, $query->topProductsLimit)),
        ];
    }
}
