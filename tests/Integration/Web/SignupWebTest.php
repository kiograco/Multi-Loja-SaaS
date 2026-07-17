<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * /app/signup — self-service account creation (Seção 19, implemented at the
 * user's explicit request). Registers the owner + first store in one step via
 * the same RegisterUserCommand/CreateTenantCommand the CLI's tenant:create
 * already uses, then signs the user in immediately.
 */
final class SignupWebTest extends WebTestCase
{
    public function testSignupPageIsPubliclyReachable(): void
    {
        $response = $this->request('GET', '/app/signup');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<form', $response->body);
    }

    public function testSuccessfulSignupCreatesAccountAndSignsIn(): void
    {
        $response = $this->request('POST', '/app/signup', [
            'storeName' => 'My New Shop',
            'email' => 'new-owner@shop.test',
            'password' => 'secret123',
        ]);

        self::assertSame(302, $response->status);
        self::assertSame('/app/dashboard', $response->headers['location']);

        $dashboard = $this->request('GET', '/app/dashboard');
        self::assertSame(200, $dashboard->status);

        // A real login with the same credentials also works afterwards.
        $this->request('POST', '/app/logout');
        $login = $this->request('POST', '/app/login', ['email' => 'new-owner@shop.test', 'password' => 'secret123']);
        self::assertSame(302, $login->status);
        self::assertSame('/app/dashboard', $login->headers['location']);
    }

    public function testSignupWithDuplicateEmailShowsError(): void
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand('taken@shop.test', 'secret123'));

        $response = $this->request('POST', '/app/signup', [
            'storeName' => 'Another Shop',
            'email' => 'taken@shop.test',
            'password' => 'secret123',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('already exists', $response->body);
    }

    public function testSignupWithBlankStoreNameShowsError(): void
    {
        $response = $this->request('POST', '/app/signup', [
            'storeName' => '',
            'email' => 'blank-store@shop.test',
            'password' => 'secret123',
        ]);

        self::assertSame(422, $response->status);
    }

    public function testSignupWithShortPasswordShowsError(): void
    {
        $response = $this->request('POST', '/app/signup', [
            'storeName' => 'My Shop',
            'email' => 'short-pass@shop.test',
            'password' => '123',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('8 caracteres', $response->body);
    }

    public function testSignupWithInvalidEmailShowsError(): void
    {
        $response = $this->request('POST', '/app/signup', [
            'storeName' => 'My Shop',
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('e-mail válido', $response->body);
    }
}
