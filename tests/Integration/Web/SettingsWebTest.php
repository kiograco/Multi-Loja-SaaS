<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

/**
 * Web counterpart of the tenant settings API: a form-driven page reusing the
 * same UpdateTenantSettingsCommand/GetTenantSettingsQuery as the JSON API, so
 * the store owner can change the store name and webhook URL after signup —
 * previously only settable once, at tenant creation.
 */
final class SettingsWebTest extends WebTestCase
{
    public function testSettingsPageShowsCurrentStoreName(): void
    {
        $this->loginAsNewOwner('settings-page@shop.test', 'secret123', 'My Original Shop');

        $response = $this->request('GET', '/app/settings');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('My Original Shop', $response->body);
    }

    public function testUpdatingSettingsPersistsAndRedirects(): void
    {
        $this->loginAsNewOwner('settings-update@shop.test', 'secret123', 'My Original Shop');

        $response = $this->request('POST', '/app/settings', [
            'storeName' => 'Renamed Shop',
            'webhookUrl' => 'https://example.com/hooks/orders',
        ]);

        self::assertSame(302, $response->status);
        self::assertSame('/app/settings', $response->headers['location']);

        $page = $this->request('GET', '/app/settings');
        self::assertStringContainsString('Renamed Shop', $page->body);
        self::assertStringContainsString('https://example.com/hooks/orders', $page->body);
    }

    public function testInvalidWebhookUrlReRendersFormWithError(): void
    {
        $this->loginAsNewOwner('settings-invalid@shop.test', 'secret123', 'My Original Shop');

        $response = $this->request('POST', '/app/settings', [
            'storeName' => 'My Original Shop',
            'webhookUrl' => 'not-a-url',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('not-a-url', $response->body);
    }

    public function testSettingsRouteRequiresAuthentication(): void
    {
        $response = $this->request('GET', '/app/settings');

        self::assertSame(302, $response->status);
        self::assertSame('/app/login', $response->headers['location']);
    }

    public function testTestWebhookButtonOnlyAppearsWhenAWebhookIsConfigured(): void
    {
        $this->loginAsNewOwner('settings-no-webhook@shop.test', 'secret123', 'My Original Shop');

        $withoutWebhook = $this->request('GET', '/app/settings');
        self::assertStringNotContainsString('Testar webhook agora', $withoutWebhook->body);

        $this->request('POST', '/app/settings', [
            'storeName' => 'My Original Shop',
            'webhookUrl' => 'https://example.com/hooks/orders',
        ]);

        $withWebhook = $this->request('GET', '/app/settings');
        self::assertStringContainsString('Testar webhook agora', $withWebhook->body);
    }

    public function testTestWebhookButtonFlashesSuccessAndRecordsTheAttempt(): void
    {
        $this->loginAsNewOwner('settings-test-webhook@shop.test', 'secret123', 'My Original Shop');
        $this->request('POST', '/app/settings', [
            'storeName' => 'My Original Shop',
            'webhookUrl' => 'https://example.com/hooks/orders',
        ]);

        $response = $this->request('POST', '/app/settings/test-webhook');

        self::assertSame(302, $response->status);
        self::assertSame('/app/settings', $response->headers['location']);

        $page = $this->request('GET', '/app/settings');
        self::assertStringContainsString('Webhook de teste entregue com sucesso', $page->body);
        self::assertStringContainsString('Teste manual', $page->body);
    }

    public function testTestWebhookButtonFlashesErrorOnFailure(): void
    {
        $this->loginAsNewOwner('settings-test-webhook-fail@shop.test', 'secret123', 'My Original Shop');
        $this->request('POST', '/app/settings', [
            'storeName' => 'My Original Shop',
            'webhookUrl' => 'https://example.com/hooks/orders',
        ]);
        $this->webhook->shouldFail = true;

        $response = $this->request('POST', '/app/settings/test-webhook');
        self::assertSame(302, $response->status);

        $page = $this->request('GET', '/app/settings');
        self::assertStringContainsString('Falha ao entregar o webhook de teste', $page->body);
    }
}
