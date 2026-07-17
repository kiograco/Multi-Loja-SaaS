<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * Product is plain CRUD (Seção 5.2) — deleting one was the missing quarter of
 * the acronym. Covers DELETE /products/{id}, tenant isolation, and that
 * historical orders keep their snapshotted product name/price afterwards
 * (CreateOrderHandler snapshots at order time, no live reference).
 */
final class ProductDeleteApiTest extends ApiTestCase
{
    private const PREFIX = '/api/v1';

    private function loginWithTenant(string $email): string
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand($email, 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => $email, 'password' => 'secret123']))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Delete Shop'], $token);

        return (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', ['email' => $email, 'password' => 'secret123']))['token'];
    }

    public function testDeletingAProductRemovesItFromTheListing(): void
    {
        $token = $this->loginWithTenant('product-delete@shop.test');
        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Disposable Widget',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $token))['id'];

        $response = $this->request('DELETE', self::PREFIX . "/products/{$productId}", [], $token);

        self::assertSame(204, $response->status);
        $list = $this->decode($this->request('GET', self::PREFIX . '/products', [], $token));
        self::assertCount(0, $list['data']);
    }

    public function testDeletingAnUnknownProductReturns404(): void
    {
        $token = $this->loginWithTenant('product-delete-404@shop.test');

        $response = $this->request('DELETE', self::PREFIX . '/products/019447b1-6a9d-7b6e-9c3c-000000000000', [], $token);

        self::assertSame(404, $response->status);
        self::assertSame('NOT_FOUND', $this->decode($response)['error']['code']);
    }

    public function testCannotDeleteAnotherTenantsProduct(): void
    {
        $ownerToken = $this->loginWithTenant('product-delete-owner@shop.test');
        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Owner Product',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $ownerToken))['id'];

        $intruderToken = $this->loginWithTenant('product-delete-intruder@shop.test');
        $response = $this->request('DELETE', self::PREFIX . "/products/{$productId}", [], $intruderToken);

        self::assertSame(404, $response->status);

        // Still there for the real owner.
        $list = $this->decode($this->request('GET', self::PREFIX . '/products', [], $ownerToken));
        self::assertCount(1, $list['data']);
    }

    public function testHistoricalOrderKeepsSnapshottedProductNameAfterDeletion(): void
    {
        $token = $this->loginWithTenant('product-delete-snapshot@shop.test');
        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Soon Gone',
            'priceCents' => 1000,
            'stockQuantity' => 5,
        ], $token))['id'];
        $orderId = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];

        $this->request('DELETE', self::PREFIX . "/products/{$productId}", [], $token);

        $order = $this->decode($this->request('GET', self::PREFIX . "/orders/{$orderId}", [], $token));
        self::assertSame('Soon Gone', $order['items'][0]['productName']);
    }
}
