<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * Covers Fase 11's acceptance criterion: accessing a protected /app/* route
 * without a session redirects to /app/login, and a valid session unlocks it.
 */
final class AuthWebTest extends WebTestCase
{
    public function testProtectedRouteRedirectsToLoginWhenUnauthenticated(): void
    {
        $response = $this->request('GET', '/app/dashboard');

        self::assertSame(302, $response->status);
        self::assertSame('/app/login', $response->headers['location']);
    }

    public function testLoginPageIsPubliclyReachable(): void
    {
        $response = $this->request('GET', '/app/login');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<form', $response->body);
    }

    public function testValidLoginCreatesSessionAndRedirectsToDashboard(): void
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand('owner@shop.test', 'secret123'));
        $userId = $this->container->userRepository()->findByEmail('owner@shop.test')?->id->value;
        $this->container->commandBus()->dispatch(new CreateTenantCommand((string) $userId, 'My Shop'));

        $login = $this->request('POST', '/app/login', ['email' => 'owner@shop.test', 'password' => 'secret123']);
        self::assertSame(302, $login->status);
        self::assertSame('/app/dashboard', $login->headers['location']);

        $dashboard = $this->request('GET', '/app/dashboard');
        self::assertSame(200, $dashboard->status);
    }

    public function testInvalidCredentialsReRenderLoginWithError(): void
    {
        $response = $this->request('POST', '/app/login', ['email' => 'nobody@shop.test', 'password' => 'wrong']);

        self::assertSame(401, $response->status);
        self::assertStringContainsString('inválid', $response->body);
    }

    public function testLogoutDestroysSessionAndLocksProtectedRoutesAgain(): void
    {
        $this->loginAsNewOwner();
        self::assertSame(200, $this->request('GET', '/app/dashboard')->status);

        $logout = $this->request('POST', '/app/logout');
        self::assertSame(302, $logout->status);
        self::assertSame('/app/login', $logout->headers['location']);

        $afterLogout = $this->request('GET', '/app/dashboard');
        self::assertSame(302, $afterLogout->status);
        self::assertSame('/app/login', $afterLogout->headers['location']);
    }
}
