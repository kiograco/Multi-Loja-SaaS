<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;

/**
 * Fase 12's acceptance criterion: the Web dashboard and the JSON API read the
 * exact same projections, so the numbers rendered in HTML must match what
 * GetDashboardSummaryQuery returns for the same tenant.
 */
final class DashboardWebTest extends WebTestCase
{
    public function testDashboardRendersTheSameNumbersAsTheQuery(): void
    {
        $tenantId = $this->loginAsNewOwner();

        $productId = $this->container->commandBus()->dispatch(new CreateProductCommand($tenantId, 'Mechanical Keyboard', 45000, 10));
        $orderId = $this->container->commandBus()->dispatch(new CreateOrderCommand(
            $tenantId,
            'Alan Turing',
            'alan@example.com',
            [['productId' => (string) $productId, 'quantity' => 2]],
        ));
        $this->container->commandBus()->dispatch(new PayOrderCommand($tenantId, (string) $orderId, 'pix'));

        $expected = $this->container->queryBus()->ask(new GetDashboardSummaryQuery($tenantId));

        $response = $this->request('GET', '/app/dashboard');

        self::assertSame(200, $response->status);
        self::assertStringContainsString((string) $expected['totals']['paidOrders'], $response->body);
        self::assertStringContainsString($expected['totals']['revenue'], $response->body);
    }

    public function testTopProductsLimitIsAdjustableViaQueryString(): void
    {
        $tenantId = $this->loginAsNewOwner();
        for ($i = 1; $i <= 7; ++$i) {
            $productId = $this->container->commandBus()->dispatch(new CreateProductCommand($tenantId, "Product {$i}", 1000, 10));
            $orderId = $this->container->commandBus()->dispatch(new CreateOrderCommand(
                $tenantId,
                'Alan Turing',
                'alan@example.com',
                [['productId' => (string) $productId, 'quantity' => 1]],
            ));
            $this->container->commandBus()->dispatch(new PayOrderCommand($tenantId, (string) $orderId, 'pix'));
        }

        $default = $this->container->queryBus()->ask(new GetDashboardSummaryQuery($tenantId));
        self::assertCount(5, $default['topProducts']);

        $response = $this->request('GET', '/app/dashboard', [], ['topProductsLimit' => '7']);
        $expanded = $this->container->queryBus()->ask(new GetDashboardSummaryQuery($tenantId, 7));

        self::assertSame(200, $response->status);
        self::assertCount(7, $expanded['topProducts']);
        self::assertStringContainsString('Product 7', $response->body);
    }
}
