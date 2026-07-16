<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Projection;

use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Queue\JobQueue;
use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Infrastructure\Persistence\Database;
use OrderHub\Infrastructure\Queue\InMemoryJobQueue;
use OrderHub\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Phase 4 acceptance: dispatching OrderCreated then PaymentReceived must leave
 * the order_summary projection — and the aggregate projections — in the correct
 * state, synchronously, within the same flow.
 */
final class OrderProjectionTest extends IntegrationTestCase
{
    private Container $container;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->container->set(Database::class, $this->database);
        $this->container->set(JobQueue::class, new InMemoryJobQueue());
        $this->tenantId = Uuid::uuid4()->toString();
    }

    public function testProjectionsReflectCreatedThenPaidOrder(): void
    {
        $bus = $this->container->commandBus();

        $productId = $bus->dispatch(new CreateProductCommand($this->tenantId, 'Notebook', 500000, 5));
        $orderId = $bus->dispatch(new CreateOrderCommand(
            $this->tenantId,
            'Katherine Johnson',
            'katherine@example.com',
            [['productId' => (string) $productId, 'quantity' => 2]],
        ));

        // After creation: summary exists as "criado", no revenue aggregated yet.
        $summary = $this->container->orderSummaryStore()->find((string) $orderId);
        self::assertNotNull($summary);
        self::assertSame('criado', $summary->status);
        self::assertSame(1000000, $summary->totalCents);
        self::assertSame([], $this->container->dailySalesStore()->seriesForTenant($this->tenantId));

        // After payment: summary flips to "pago" and aggregates update.
        $bus->dispatch(new PayOrderCommand($this->tenantId, (string) $orderId, 'pix'));

        $paid = $this->container->orderSummaryStore()->find((string) $orderId);
        self::assertNotNull($paid);
        self::assertSame('pago', $paid->status);

        $series = $this->container->dailySalesStore()->seriesForTenant($this->tenantId);
        self::assertCount(1, $series);
        self::assertSame(1, $series[0]['ordersCount']);
        self::assertSame(1000000, $series[0]['revenueCents']);

        $top = $this->container->topProductsStore()->topForTenant($this->tenantId, 5);
        self::assertCount(1, $top);
        self::assertSame(2, $top[0]['unitsSold']);
        self::assertSame(1000000, $top[0]['revenueCents']);
    }

    public function testProjectionRebuildReproducesStateFromEvents(): void
    {
        $bus = $this->container->commandBus();
        $productId = $bus->dispatch(new CreateProductCommand($this->tenantId, 'Mouse', 15000, 100));
        $orderId = $bus->dispatch(new CreateOrderCommand(
            $this->tenantId,
            'Margaret Hamilton',
            'margaret@example.com',
            [['productId' => (string) $productId, 'quantity' => 3]],
        ));
        $bus->dispatch(new PayOrderCommand($this->tenantId, (string) $orderId, 'pix'));

        // Wipe every projection, then rebuild purely from the event store.
        $this->container->orderSummaryStore()->truncate();
        $this->container->dailySalesStore()->truncate();
        $this->container->topProductsStore()->truncate();
        self::assertNull($this->container->orderSummaryStore()->find((string) $orderId));

        $this->container->projectionRebuilder()->rebuildAll();

        $rebuilt = $this->container->orderSummaryStore()->find((string) $orderId);
        self::assertNotNull($rebuilt);
        self::assertSame('pago', $rebuilt->status);
        self::assertSame(45000, $rebuilt->totalCents);
        self::assertSame(45000, $this->container->dailySalesStore()->seriesForTenant($this->tenantId)[0]['revenueCents']);
    }
}
