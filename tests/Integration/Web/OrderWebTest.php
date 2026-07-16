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

    public function testOrdersListShowsPaginationOnlyWhenMoreThanOnePageExists(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->createOrder($tenantId);

        $single = $this->request('GET', '/app/orders');
        self::assertStringNotContainsString('class="pagination"', $single->body);

        for ($i = 0; $i < 20; ++$i) {
            $this->createOrder($tenantId);
        }

        $firstPage = $this->request('GET', '/app/orders');
        self::assertStringContainsString('class="pagination"', $firstPage->body);
        self::assertStringContainsString('Página 1 de 2', $firstPage->body);

        $secondPage = $this->request('GET', '/app/orders', [], ['page' => '2']);
        self::assertStringContainsString('Página 2 de 2', $secondPage->body);
    }

    public function testInvoiceIsNotReadyBeforeTheWorkerProcessesTheJob(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix']);

        $response = $this->request('GET', "/app/orders/{$orderId}/invoice");

        self::assertSame(404, $response->status);
    }

    public function testInvoiceDownloadsAsPdfOnceTheWorkerHasProcessedIt(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix']);

        $this->container->worker()->run(4);

        $response = $this->request('GET', "/app/orders/{$orderId}/invoice");

        self::assertSame(200, $response->status);
        self::assertSame('application/pdf', $response->headers['content-type']);
        self::assertStringStartsWith('%PDF-1.4', $response->body);
    }

    public function testOrderDetailShowsInvoiceLinkOnlyAfterPayment(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);

        $beforePayment = $this->request('GET', "/app/orders/{$orderId}");
        self::assertStringNotContainsString('Baixar nota fiscal', $beforePayment->body);

        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix']);

        $afterPayment = $this->request('GET', "/app/orders/{$orderId}");
        self::assertStringContainsString('Baixar nota fiscal', $afterPayment->body);
    }

    public function testPerPageIsAdjustableViaQueryString(): void
    {
        $tenantId = $this->loginAsNewOwner();
        for ($i = 0; $i < 15; ++$i) {
            $this->createOrder($tenantId);
        }

        // Default perPage (20) fits all 15 on a single page.
        $default = $this->request('GET', '/app/orders');
        self::assertStringNotContainsString('class="pagination"', $default->body);

        // Shrinking perPage to 10 forces a second page.
        $withCustomPerPage = $this->request('GET', '/app/orders', [], ['perPage' => '10']);
        self::assertStringContainsString('Página 1 de 2', $withCustomPerPage->body);
    }

    public function testOrderCanBeDeliveredAfterShippingAndCompletesTheLifecycle(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], [], ['hx-request' => 'true']);
        $this->request('POST', "/app/orders/{$orderId}/ship", ['trackingCode' => 'BR123'], [], ['hx-request' => 'true']);

        $response = $this->request('POST', "/app/orders/{$orderId}/deliver", [], [], ['hx-request' => 'true']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('badge-entregue', $response->body);

        $detail = $this->request('GET', "/app/orders/{$orderId}");
        self::assertStringContainsString('OrderDelivered', $detail->body);
        // Final state: no more action buttons, cycle is complete.
        self::assertStringNotContainsString('Marcar como', $detail->body);
        self::assertStringNotContainsString('Cancelar pedido', $detail->body);
    }

    public function testCancelButtonIsAvailableForPaidOrdersWithReinforcedConfirmation(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], [], ['hx-request' => 'true']);

        $response = $this->request('GET', "/app/orders/{$orderId}");

        self::assertStringContainsString('Cancelar pedido', $response->body);
        self::assertStringContainsString('estorno manual', $response->body);
    }

    public function testCancelButtonIsGoneOnceShipped(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $orderId = $this->createOrder($tenantId);
        $this->request('POST', "/app/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], [], ['hx-request' => 'true']);
        $this->request('POST', "/app/orders/{$orderId}/ship", ['trackingCode' => 'BR123'], [], ['hx-request' => 'true']);

        $response = $this->request('GET', "/app/orders/{$orderId}");

        self::assertStringNotContainsString('Cancelar pedido', $response->body);
    }
}
