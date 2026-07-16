<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * DispatchWebhookJob always posted to the tenant's webhook, but a failure was
 * only ever visible in the server log. This covers the queryable delivery
 * history and the on-demand test endpoint that close that visibility gap
 * (Seção 6/18).
 */
final class WebhookApiTest extends ApiTestCase
{
    private const PREFIX = '/api/v1';

    private function loginWithWebhook(string $email, string $webhookUrl = 'https://example.com/hooks/orders'): string
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand($email, 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => $email, 'password' => 'secret123']))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Webhook Shop', 'webhook_url' => $webhookUrl], $token);

        return (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => $email, 'password' => 'secret123']))['token'];
    }

    public function testSuccessfulPaymentRecordsADeliveryAttempt(): void
    {
        $token = $this->loginWithWebhook('webhook-success@shop.test');

        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Widget',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $token))['id'];
        $orderId = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];
        $this->request('POST', self::PREFIX . "/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], $token);

        $this->container->worker()->run(4);

        $deliveries = $this->decode($this->request('GET', self::PREFIX . '/webhooks/deliveries', [], $token))['data'];

        self::assertCount(1, $deliveries);
        self::assertTrue($deliveries[0]['success']);
        self::assertSame($orderId, $deliveries[0]['orderId']);
        self::assertSame(200, $deliveries[0]['responseCode']);
    }

    public function testFailedDeliveryIsRecordedAndVisible(): void
    {
        $token = $this->loginWithWebhook('webhook-failure@shop.test');
        $this->webhook->shouldFail = true;

        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Widget',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $token))['id'];
        $orderId = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];
        $this->request('POST', self::PREFIX . "/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], $token);

        // The worker retries on failure; run enough attempts to exhaust the
        // other three side-effect jobs plus the failing webhook dispatch.
        $this->container->worker()->run(4);

        $deliveries = $this->decode($this->request('GET', self::PREFIX . '/webhooks/deliveries', [], $token))['data'];

        self::assertCount(1, $deliveries);
        self::assertFalse($deliveries[0]['success']);
        self::assertNotNull($deliveries[0]['error']);
    }

    public function testOnDemandTestWebhookRecordsAManualAttempt(): void
    {
        $token = $this->loginWithWebhook('webhook-test@shop.test');

        $response = $this->request('POST', self::PREFIX . '/webhooks/test', [], $token);

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertTrue($body['success']);
        self::assertSame(200, $body['responseCode']);

        $deliveries = $this->decode($this->request('GET', self::PREFIX . '/webhooks/deliveries', [], $token))['data'];
        self::assertCount(1, $deliveries);
        self::assertNull($deliveries[0]['orderId']);
    }

    public function testTestWebhookFailsWithConflictWhenNoneConfigured(): void
    {
        $token = $this->loginWithWebhook('webhook-none@shop.test', '');

        $response = $this->request('POST', self::PREFIX . '/webhooks/test', [], $token);

        self::assertSame(409, $response->status);
    }
}
