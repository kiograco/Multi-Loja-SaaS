<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * End-to-end HTTP journey: register → login → create tenant → re-login (now
 * tenant-scoped) → create product → create order → pay → ship, then read back
 * through the projections. Proves Phase 5's acceptance criterion.
 */
final class OrderFlowApiTest extends ApiTestCase
{
    private const PREFIX = '/api/v1';

    public function testCompleteOrderLifecycleOverHttp(): void
    {
        // Arrange: a user exists (no public register endpoint, so use the bus).
        $this->container->commandBus()->dispatch(new RegisterUserCommand('owner@shop.test', 'secret123'));

        // Login without a tenant yet.
        $login = $this->request('POST', self::PREFIX . '/auth/login', ['email' => 'owner@shop.test', 'password' => 'secret123']);
        self::assertSame(200, $login->status);
        $token = (string) $this->decode($login)['token'];

        // Create a store.
        $tenantResp = $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'My Shop'], $token);
        self::assertSame(201, $tenantResp->status);

        // Re-login so the token carries tenant_id.
        $login2 = $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'owner@shop.test', 'password' => 'secret123']));
        self::assertNotNull($login2['tenantId']);
        $token = (string) $login2['token'];

        // Create a product.
        $productResp = $this->request('POST', self::PREFIX . '/products', [
            'name' => 'Mechanical Keyboard',
            'priceCents' => 45000,
            'stockQuantity' => 10,
        ], $token);
        self::assertSame(201, $productResp->status);
        $productId = (string) $this->decode($productResp)['id'];

        // Create an order for 2 units.
        $orderResp = $this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Alan Turing',
            'customerEmail' => 'alan@example.com',
            'items' => [['productId' => $productId, 'quantity' => 2]],
        ], $token);
        self::assertSame(201, $orderResp->status);
        $orderId = (string) $this->decode($orderResp)['id'];

        // Pay it.
        $payResp = $this->request('POST', self::PREFIX . "/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], $token);
        self::assertSame(200, $payResp->status);

        // Ship it.
        $shipResp = $this->request('POST', self::PREFIX . "/orders/{$orderId}/ship", ['trackingCode' => 'BR123'], $token);
        self::assertSame(200, $shipResp->status);

        // Deliver it — the final step of the lifecycle.
        $deliverResp = $this->request('POST', self::PREFIX . "/orders/{$orderId}/deliver", [], $token);
        self::assertSame(200, $deliverResp->status);
        self::assertSame('entregue', $this->decode($deliverResp)['status']);

        // Read back the projection.
        $show = $this->decode($this->request('GET', self::PREFIX . "/orders/{$orderId}", [], $token));
        self::assertSame('entregue', $show['status']);
        self::assertSame(90000, $show['totalCents']);
        self::assertSame('BR123', $show['trackingCode']);

        // Timeline reflects the full chronological event history.
        $timeline = $this->decode($this->request('GET', self::PREFIX . "/orders/{$orderId}/timeline", [], $token))['data'];
        self::assertSame(
            ['OrderCreated', 'PaymentReceived', 'OrderShipped', 'OrderDelivered'],
            array_column($timeline, 'type'),
        );

        // List filtered by status.
        $list = $this->decode($this->request('GET', self::PREFIX . '/orders', [], $token, 'entregue'));
        self::assertSame(1, $list['meta']['total']);

        // Dashboard reflects the paid order.
        $dashboard = $this->decode($this->request('GET', self::PREFIX . '/dashboard/summary', [], $token));
        self::assertSame(1, $dashboard['totals']['paidOrders']);
        self::assertSame('900.00', $dashboard['totals']['revenue']);
        self::assertCount(1, $dashboard['topProducts']);
    }

    public function testOrderCanBeCancelledAfterPaymentButNotAfterShipping(): void
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand('cancel-flow@shop.test', 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'cancel-flow@shop.test', 'password' => 'secret123']))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Cancel Shop'], $token);
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'cancel-flow@shop.test', 'password' => 'secret123']))['token'];

        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Widget',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $token))['id'];

        // A paid order can still be cancelled.
        $orderId = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Grace Hopper',
            'customerEmail' => 'grace@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];
        $this->request('POST', self::PREFIX . "/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], $token);

        $cancelResp = $this->request('POST', self::PREFIX . "/orders/{$orderId}/cancel", ['reason' => 'cliente desistiu'], $token);
        self::assertSame(200, $cancelResp->status);
        self::assertSame('cancelado', $this->decode($cancelResp)['status']);

        // A shipped order can no longer be cancelled.
        $orderId2 = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];
        $this->request('POST', self::PREFIX . "/orders/{$orderId2}/pay", ['paymentMethod' => 'pix'], $token);
        $this->request('POST', self::PREFIX . "/orders/{$orderId2}/ship", ['trackingCode' => 'BR999'], $token);

        $rejectedCancel = $this->request('POST', self::PREFIX . "/orders/{$orderId2}/cancel", ['reason' => 'tarde demais'], $token);
        self::assertSame(409, $rejectedCancel->status);
    }

    public function testOrderCannotBeDeliveredBeforeBeingShipped(): void
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand('deliver-guard@shop.test', 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'deliver-guard@shop.test', 'password' => 'secret123']))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Guard Shop'], $token);
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'deliver-guard@shop.test', 'password' => 'secret123']))['token'];

        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Widget',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $token))['id'];
        $orderId = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Grace Hopper',
            'customerEmail' => 'grace@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];
        $this->request('POST', self::PREFIX . "/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], $token);

        // Not shipped yet — delivering must fail.
        $resp = $this->request('POST', self::PREFIX . "/orders/{$orderId}/deliver", [], $token);
        self::assertSame(409, $resp->status);
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        $resp = $this->request('GET', self::PREFIX . '/products');
        self::assertSame(401, $resp->status);
        self::assertSame('UNAUTHENTICATED', $this->decode($resp)['error']['code']);
    }

    public function testValidationErrorHasUniformShape(): void
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand('v@shop.test', 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'v@shop.test', 'password' => 'secret123']))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Shop V'], $token);
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => 'v@shop.test', 'password' => 'secret123']))['token'];

        // Missing required 'name'.
        $resp = $this->request('POST', self::PREFIX . '/products', ['priceCents' => 100, 'stockQuantity' => 1], $token);
        self::assertSame(422, $resp->status);
        self::assertSame('VALIDATION_ERROR', $this->decode($resp)['error']['code']);
    }
}
