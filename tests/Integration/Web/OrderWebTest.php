<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;

/**
 * Fase 14's acceptance criteria: HTMX actions (pay/ship/cancel) swap the
 * status panel without a full page reload when the request carries
 * `HX-Request: true`, fall back to a redirect otherwise, and the event
 * timeline reflects exactly what's in the event store, in order.
 */
final class OrderWebTest extends WebTestCase
{
    private function createOrder(string $tenantId): string
    {
        $productId = $this->container->commandBus()->dispatch(new CreateProductCommand($tenantId, 'Widget', 10000, 10));

        return (string) $this->container->commandBus()->dispatch(new CreateOrderCommand(
            $tenantId,
            'Alan Turing',
            'alan@example.com',
            [['productId' => (string) $productId, 'quantity' => 2]],
        ));
    }

    public function testHtmxPayReturnsOnlyTheStatusPanelFragment(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);

        $response = $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], [], ['hx-request' => 'true']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('id="order-status-panel"', $response->body);
        self::assertStringContainsString('badge-pago', $response->body);
        self::assertStringNotContainsString('<html', $response->body);
    }

    public function testNonHtmxPayRedirectsBackToOrderDetail(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);

        $response = $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix']);

        self::assertSame(302, $response->status);
        self::assertSame("/app/orders/{$orderId}", $response->headers['location']);
    }

    public function testOrderDetailShowsChronologicalEventTimeline(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], [], ['hx-request' => 'true']);
        $this->request('POST', "/app/orders/{$orderId}/ship", ['trackingCode' => 'BR123'], [], ['hx-request' => 'true']);

        $response = $this->request('GET', "/app/orders/{$orderId}");

        self::assertSame(200, $response->status);
        $createdPos = strpos($response->body, 'OrderCreated');
        $paidPos = strpos($response->body, 'PaymentReceived');
        $shippedPos = strpos($response->body, 'OrderShipped');

        self::assertNotFalse($createdPos);
        self::assertNotFalse($paidPos);
        self::assertNotFalse($shippedPos);
        self::assertTrue($createdPos < $paidPos && $paidPos < $shippedPos);
        self::assertStringContainsString('BR123', $response->body);
    }

    public function testOrdersListCanBeFilteredByStatus(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], [], ['hx-request' => 'true']);

        $response = $this->request('GET', '/app/orders', [], ['status' => 'pago']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Alan Turing', $response->body);
    }
}
