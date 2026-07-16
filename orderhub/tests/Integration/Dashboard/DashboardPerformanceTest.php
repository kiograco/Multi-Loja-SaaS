<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Dashboard;

use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;
use OrderHub\Application\Queue\JobQueue;
use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Infrastructure\Persistence\Database;
use OrderHub\Infrastructure\Queue\InMemoryJobQueue;
use OrderHub\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Phase 8 acceptance: with a large history, the dashboard query stays fast
 * because it reads pre-aggregated projections instead of recomputing from raw
 * events. Seeds 1000 paid orders, then asserts the query is well under 200ms.
 */
final class DashboardPerformanceTest extends IntegrationTestCase
{
    private const ORDERS = 1000;

    public function testDashboardQueryIsFastOverManyOrders(): void
    {
        $container = new Container();
        $container->set(Database::class, $this->database);
        $container->set(JobQueue::class, new InMemoryJobQueue());

        $tenantId = Uuid::uuid4()->toString();
        $bus = $container->commandBus();

        $productId = (string) $bus->dispatch(new CreateProductCommand($tenantId, 'Sticker Pack', 1000, self::ORDERS * 2));

        for ($i = 0; $i < self::ORDERS; ++$i) {
            $orderId = (string) $bus->dispatch(new CreateOrderCommand(
                $tenantId,
                'Customer ' . $i,
                'customer' . $i . '@example.com',
                [['productId' => $productId, 'quantity' => 1]],
            ));
            $bus->dispatch(new PayOrderCommand($tenantId, $orderId, 'pix'));
        }

        $query = new GetDashboardSummaryQuery($tenantId);

        $start = hrtime(true);
        $summary = $bus = $container->queryBus()->ask($query);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertSame(self::ORDERS, $summary['totals']['paidOrders']);
        self::assertSame(self::ORDERS * 1000, $summary['totals']['revenueCents']);
        self::assertLessThan(
            200.0,
            $elapsedMs,
            \sprintf('Dashboard query took %.2fms, expected < 200ms (should read projections, not events).', $elapsedMs),
        );
    }
}
