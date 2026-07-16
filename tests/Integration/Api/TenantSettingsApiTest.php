<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * Tenant::rename()/configureWebhook() already existed on the domain aggregate
 * but had no command/route reaching them after tenant creation — this covers
 * the new GET/PATCH /tenants/me pair that closes that gap.
 */
final class TenantSettingsApiTest extends ApiTestCase
{
    private const PREFIX = '/api/v1';

    private function loginWithTenant(string $email): string
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand($email, 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ]))['token'];

        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Original Name'], $token);

        return (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ]))['token'];
    }

    public function testShowReturnsCurrentSettings(): void
    {
        $token = $this->loginWithTenant('settings-show@shop.test');

        $response = $this->request('GET', self::PREFIX . '/tenants/me', [], $token);

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertSame('Original Name', $body['storeName']);
        self::assertNull($body['webhookUrl']);
    }

    public function testUpdateRenamesStoreAndSetsWebhook(): void
    {
        $token = $this->loginWithTenant('settings-update@shop.test');

        $response = $this->request('PATCH', self::PREFIX . '/tenants/me', [
            'store_name' => 'Renamed Store',
            'webhook_url' => 'https://example.com/hooks/orders',
        ], $token);

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertSame('Renamed Store', $body['storeName']);
        self::assertSame('https://example.com/hooks/orders', $body['webhookUrl']);

        // Persisted, not just echoed back.
        $show = $this->decode($this->request('GET', self::PREFIX . '/tenants/me', [], $token));
        self::assertSame('Renamed Store', $show['storeName']);
        self::assertSame('https://example.com/hooks/orders', $show['webhookUrl']);
    }

    public function testUpdateRejectsInvalidWebhookUrl(): void
    {
        $token = $this->loginWithTenant('settings-invalid@shop.test');

        $response = $this->request('PATCH', self::PREFIX . '/tenants/me', [
            'store_name' => 'Original Name',
            'webhook_url' => 'not-a-url',
        ], $token);

        self::assertSame(422, $response->status);
        self::assertSame('INVALID_INPUT', $this->decode($response)['error']['code']);
    }
}
