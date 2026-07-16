<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * Tenant::findByOwner() already supported an owner with several stores since
 * Fase 3, but the Web session only ever carried the first one, with no way to
 * reach the others. This covers the "trocar de loja" switch added to close
 * that gap.
 */
final class MultiStoreWebTest extends WebTestCase
{
    /**
     * @return array{firstTenantId: string, secondTenantId: string}
     */
    private function loginWithTwoStores(): array
    {
        $userId = (string) $this->container->commandBus()->dispatch(new RegisterUserCommand('multi-store@shop.test', 'secret123'));
        $firstTenantId = (string) $this->container->commandBus()->dispatch(new CreateTenantCommand($userId, 'Loja Um'));
        $secondTenantId = (string) $this->container->commandBus()->dispatch(new CreateTenantCommand($userId, 'Loja Dois'));

        $login = $this->request('POST', '/app/login', ['email' => 'multi-store@shop.test', 'password' => 'secret123']);
        self::assertSame(302, $login->status);

        return ['firstTenantId' => $firstTenantId, 'secondTenantId' => $secondTenantId];
    }

    public function testNavShowsStoreSwitcherOnlyWhenOwningMoreThanOneStore(): void
    {
        $this->loginAsNewOwner('single-store@shop.test', 'secret123', 'Loja Única');

        $response = $this->request('GET', '/app/dashboard');

        self::assertStringNotContainsString('store-switcher', $response->body);
    }

    public function testDashboardDefaultsToTheFirstStoreAndListsBothInTheSwitcher(): void
    {
        $stores = $this->loginWithTwoStores();

        $response = $this->request('GET', '/app/dashboard');

        self::assertStringContainsString('store-switcher', $response->body);
        self::assertStringContainsString('Loja Um', $response->body);
        self::assertStringContainsString('Loja Dois', $response->body);
        self::assertStringContainsString('/app/switch-tenant/' . $stores['secondTenantId'], $response->body);
    }

    public function testSwitchingStoreChangesTheActiveTenantWithoutNewLogin(): void
    {
        $stores = $this->loginWithTwoStores();

        $switchResp = $this->request('POST', '/app/switch-tenant/' . $stores['secondTenantId']);
        self::assertSame(302, $switchResp->status);
        self::assertSame('/app/dashboard', $switchResp->headers['location']);

        // Now the switcher offers the FIRST store to switch back to.
        $response = $this->request('GET', '/app/dashboard');
        self::assertStringContainsString('/app/switch-tenant/' . $stores['firstTenantId'], $response->body);
        self::assertStringNotContainsString('/app/switch-tenant/' . $stores['secondTenantId'], $response->body);
    }

    public function testCannotSwitchToAStoreNotOwnedByTheUser(): void
    {
        $this->loginWithTwoStores();

        $intruderTenantId = (string) $this->container->commandBus()->dispatch(new CreateTenantCommand(
            (string) $this->container->commandBus()->dispatch(new RegisterUserCommand('other-owner@shop.test', 'secret123')),
            'Loja de Outro Dono',
        ));

        $response = $this->request('POST', '/app/switch-tenant/' . $intruderTenantId);

        self::assertSame(403, $response->status);
    }
}
