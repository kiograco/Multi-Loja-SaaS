<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * ListProductsQuery loaded every product for a tenant unbounded — fine for a
 * handful of products, a usability/performance problem past a few dozen. This
 * covers the paginated, name-searchable GET /products that replaced it
 * (Seção 18), keeping API/Web parity.
 */
final class ProductSearchApiTest extends ApiTestCase
{
    private const PREFIX = '/api/v1';

    private function loginWithProducts(string $email, int $count): string
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand($email, 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => $email, 'password' => 'secret123']))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Search Shop'], $token);
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => $email, 'password' => 'secret123']))['token'];

        for ($i = 1; $i <= $count; ++$i) {
            $this->request('POST', self::PREFIX . '/products', [
                'name' => "Product {$i}",
                'priceCents' => 1000,
                'stockQuantity' => 5,
            ], $token);
        }

        return $token;
    }

    public function testProductsAreSearchableByName(): void
    {
        $token = $this->loginWithProducts('product-search@shop.test', 0);
        $this->request('POST', self::PREFIX . '/products', ['name' => 'Mechanical Keyboard', 'priceCents' => 45000, 'stockQuantity' => 10], $token);
        $this->request('POST', self::PREFIX . '/products', ['name' => 'Wireless Mouse', 'priceCents' => 9000, 'stockQuantity' => 20], $token);

        $response = $this->decode($this->request('GET', self::PREFIX . '/products', [], $token, query: ['search' => 'keyboard']));

        self::assertCount(1, $response['data']);
        self::assertSame('Mechanical Keyboard', $response['data'][0]['name']);
    }

    public function testProductsListIsPaginated(): void
    {
        $token = $this->loginWithProducts('product-pagination@shop.test', 25);

        $firstPage = $this->decode($this->request('GET', self::PREFIX . '/products', [], $token));

        self::assertCount(20, $firstPage['data']);
        self::assertSame(25, $firstPage['meta']['total']);
        self::assertSame(2, $firstPage['meta']['totalPages']);

        $secondPage = $this->decode($this->request('GET', self::PREFIX . '/products', [], $token, query: ['page' => '2']));
        self::assertCount(5, $secondPage['data']);
    }
}
