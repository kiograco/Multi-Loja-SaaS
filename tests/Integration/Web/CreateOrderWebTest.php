<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Command\CreateProduct\CreateProductCommand;

/**
 * /app/orders/new — a manual order-creation form for the merchant panel.
 * Reuses the same CreateOrderCommand the API already exposed; the Web
 * controller just orchestrates a multi-row product/quantity form.
 */
final class CreateOrderWebTest extends WebTestCase
{
    private function createProduct(string $tenantId, string $name, int $priceCents, int $stock): string
    {
        return (string) $this->container->commandBus()->dispatch(new CreateProductCommand($tenantId, $name, $priceCents, $stock));
    }

    public function testNewOrderFormListsExistingProducts(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 10);

        $response = $this->request('GET', '/app/orders/new');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Mechanical Keyboard', $response->body);
    }

    public function testNewOrderFormShowsGuidanceWhenNoProductsExist(): void
    {
        $this->loginAsNewOwner();

        $response = $this->request('GET', '/app/orders/new');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Nenhum produto cadastrado', $response->body);
    }

    public function testCreatingAnOrderWithOneItemRedirectsToItsDetailPage(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $productId = $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 10);

        $response = $this->request('POST', '/app/orders/new', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'productId' => [$productId, '', '', '', ''],
            'quantity' => ['2', '1', '1', '1', '1'],
        ]);

        self::assertSame(302, $response->status);
        self::assertStringStartsWith('/app/orders/', $response->headers['location']);

        $detail = $this->request('GET', $response->headers['location']);
        self::assertStringContainsString('Ada Lovelace', $detail->body);
        self::assertStringContainsString('Mechanical Keyboard', $detail->body);
    }

    public function testCreatingAnOrderWithMultipleItemsCombinesThem(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $keyboardId = $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 10);
        $mouseId = $this->createProduct($tenantId, 'Wireless Mouse', 9000, 20);

        $response = $this->request('POST', '/app/orders/new', [
            'customerName' => 'Grace Hopper',
            'customerEmail' => 'grace@example.com',
            'productId' => [$keyboardId, $mouseId, '', '', ''],
            'quantity' => ['1', '3', '1', '1', '1'],
        ]);

        self::assertSame(302, $response->status);
        $detail = $this->request('GET', $response->headers['location']);
        self::assertStringContainsString('Mechanical Keyboard', $detail->body);
        self::assertStringContainsString('Wireless Mouse', $detail->body);
    }

    public function testCreatingAnOrderWithoutAnyItemSelectedShowsError(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 10);

        $response = $this->request('POST', '/app/orders/new', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'productId' => ['', '', '', '', ''],
            'quantity' => ['1', '1', '1', '1', '1'],
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Selecione ao menos um produto', $response->body);
    }

    public function testCreatingAnOrderWithBlankCustomerNameShowsError(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $productId = $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 10);

        $response = $this->request('POST', '/app/orders/new', [
            'customerName' => '',
            'customerEmail' => 'ada@example.com',
            'productId' => [$productId, '', '', '', ''],
            'quantity' => ['1', '1', '1', '1', '1'],
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Nome do cliente', $response->body);
    }

    public function testCreatingAnOrderWithQuantityExceedingStockShowsError(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $productId = $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 3);

        $response = $this->request('POST', '/app/orders/new', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'productId' => [$productId],
            'quantity' => ['4'],
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('stock', strtolower($response->body));
    }

    public function testCreatingAnOrderWithTheSameProductTwiceShowsError(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $productId = $this->createProduct($tenantId, 'Mechanical Keyboard', 45000, 10);

        $response = $this->request('POST', '/app/orders/new', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'productId' => [$productId, $productId],
            'quantity' => ['1', '2'],
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('more than once', $response->body);
    }

    public function testNewOrderRouteRequiresAuthentication(): void
    {
        $response = $this->request('GET', '/app/orders/new');

        self::assertSame(302, $response->status);
        self::assertSame('/app/login', $response->headers['location']);
    }
}
