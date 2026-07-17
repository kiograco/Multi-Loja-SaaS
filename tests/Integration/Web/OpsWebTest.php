<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Queue\QueuedJob;

/**
 * /app/ops — the Web home for order:replay, queue:retry-dlq and
 * projection:rebuild (Seção 19, exposed at explicit user request despite the
 * spec's own security note: retry-dlq/rebuild act across every tenant on the
 * instance, not just the caller's store, since neither the queue nor the
 * event store is tenant-partitioned).
 */
final class OpsWebTest extends WebTestCase
{
    public function testOpsPageRequiresAuthentication(): void
    {
        $response = $this->request('GET', '/app/ops');

        self::assertSame(302, $response->status);
        self::assertSame('/app/login', $response->headers['location']);
    }

    public function testReplayReconstructsOrderFromEventStore(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $productId = $this->container->commandBus()->dispatch(new CreateProductCommand($tenantId, 'Widget', 5000, 10));
        $orderId = $this->container->commandBus()->dispatch(new CreateOrderCommand(
            $tenantId,
            'Alan Turing',
            'alan@example.com',
            [['productId' => (string) $productId, 'quantity' => 2]],
        ));
        $this->container->commandBus()->dispatch(new PayOrderCommand($tenantId, (string) $orderId, 'pix'));

        $response = $this->request('POST', '/app/ops/replay', ['orderId' => (string) $orderId]);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('badge-pago', $response->body);
        self::assertStringContainsString('Widget', $response->body);
        self::assertStringContainsString('OrderCreated', $response->body);
        self::assertStringContainsString('PaymentReceived', $response->body);
    }

    public function testReplayOfAnotherTenantsOrderIsNotFound(): void
    {
        $ownerTenantId = $this->loginAsNewOwner('replay-owner@shop.test', 'secret123', 'Owner Shop');
        $productId = $this->container->commandBus()->dispatch(new CreateProductCommand($ownerTenantId, 'Widget', 5000, 10));
        $orderId = $this->container->commandBus()->dispatch(new CreateOrderCommand(
            $ownerTenantId,
            'Ada Lovelace',
            'ada@example.com',
            [['productId' => (string) $productId, 'quantity' => 1]],
        ));

        $this->request('POST', '/app/logout');
        $this->loginAsNewOwner('replay-intruder@shop.test', 'secret123', 'Intruder Shop');

        $response = $this->request('POST', '/app/ops/replay', ['orderId' => (string) $orderId]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('não encontrado', $response->body);
    }

    public function testReplayWithBlankOrderIdShowsError(): void
    {
        $this->loginAsNewOwner();

        $response = $this->request('POST', '/app/ops/replay', ['orderId' => '']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Informe o ID do pedido', $response->body);
    }

    public function testRetryDeadLetterQueueRequeuesJobs(): void
    {
        $this->loginAsNewOwner();
        $this->container->jobQueue()->deadLetter(
            new QueuedJob('test-dlq-job-1', 'DecrementStock', ['orderId' => 'irrelevant']),
            'simulated failure for the test',
        );

        $response = $this->request('POST', '/app/ops/retry-dlq');

        self::assertSame(302, $response->status);
        self::assertSame('/app/ops', $response->headers['location']);

        $page = $this->request('GET', '/app/ops');
        self::assertStringContainsString('1 job(s) reenfileirados', $page->body);
    }

    public function testRebuildProjectionKeepsDataCorrect(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $productId = $this->container->commandBus()->dispatch(new CreateProductCommand($tenantId, 'Widget', 5000, 10));
        $orderId = $this->container->commandBus()->dispatch(new CreateOrderCommand(
            $tenantId,
            'Alan Turing',
            'alan@example.com',
            [['productId' => (string) $productId, 'quantity' => 1]],
        ));
        $this->container->commandBus()->dispatch(new PayOrderCommand($tenantId, (string) $orderId, 'pix'));

        $response = $this->request('POST', '/app/ops/rebuild-projection', ['name' => 'order_summary']);

        self::assertSame(302, $response->status);
        $page = $this->request('GET', '/app/ops');
        self::assertStringContainsString('order_summary', $page->body);

        $orderPage = $this->request('GET', "/app/orders/{$orderId}");
        self::assertSame(200, $orderPage->status);
        self::assertStringContainsString('badge-pago', $orderPage->body);
    }

    public function testRebuildProjectionWithUnknownNameFlashesError(): void
    {
        $this->loginAsNewOwner();

        $response = $this->request('POST', '/app/ops/rebuild-projection', ['name' => 'not_a_real_projection']);

        self::assertSame(302, $response->status);
        $page = $this->request('GET', '/app/ops');
        self::assertStringContainsString('desconhecida', $page->body);
    }
}
