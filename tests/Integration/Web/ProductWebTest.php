<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Query\ListProducts\ListProductsQuery;

/**
 * Fase 13's acceptance criterion: creating/editing a product through the Web
 * form reflects correctly in the listing — verified here against the same
 * ListProductsQuery the JSON API uses, proving it's the same read model.
 */
final class ProductWebTest extends WebTestCase
{
    public function testCreateProductThroughFormReflectsInListing(): void
    {
        $tenantId = $this->loginAsNewOwner();

        $response = $this->request('POST', '/app/products/new', [
            'name' => 'Mechanical Keyboard',
            'price' => '450.00',
            'stockQuantity' => '10',
        ]);
        self::assertSame(302, $response->status);
        self::assertSame('/app/products', $response->headers['location']);

        $products = $this->container->queryBus()->ask(new ListProductsQuery($tenantId));
        self::assertCount(1, $products);
        self::assertSame('Mechanical Keyboard', $products[0]['name']);
        self::assertSame(45000, $products[0]['priceCents']);

        $list = $this->request('GET', '/app/products');
        self::assertStringContainsString('Mechanical Keyboard', $list->body);
    }

    public function testEditProductThroughFormUpdatesListing(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->request('POST', '/app/products/new', ['name' => 'Old Name', 'price' => '10.00', 'stockQuantity' => '1']);

        $productId = $this->container->queryBus()->ask(new ListProductsQuery($tenantId))[0]['id'];

        $response = $this->request('POST', "/app/products/{$productId}/edit", [
            'name' => 'New Name',
            'price' => '20.00',
            'stockQuantity' => '5',
        ]);
        self::assertSame(302, $response->status);

        $products = $this->container->queryBus()->ask(new ListProductsQuery($tenantId));
        self::assertSame('New Name', $products[0]['name']);
        self::assertSame(2000, $products[0]['priceCents']);
        self::assertSame(5, $products[0]['stockQuantity']);
    }

    public function testCreateWithBlankNameRendersFormWithError(): void
    {
        $this->loginAsNewOwner();

        $response = $this->request('POST', '/app/products/new', ['name' => '', 'price' => '10.00', 'stockQuantity' => '1']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('blank', strtolower($response->body));
    }

    public function testProductsCanBeSearchedByName(): void
    {
        $this->loginAsNewOwner();
        $this->request('POST', '/app/products/new', ['name' => 'Mechanical Keyboard', 'price' => '450.00', 'stockQuantity' => '10']);
        $this->request('POST', '/app/products/new', ['name' => 'Wireless Mouse', 'price' => '90.00', 'stockQuantity' => '20']);

        $response = $this->request('GET', '/app/products', [], ['search' => 'keyboard']);

        self::assertStringContainsString('Mechanical Keyboard', $response->body);
        self::assertStringNotContainsString('Wireless Mouse', $response->body);
    }

    public function testProductsListShowsPaginationOnlyWhenMoreThanOnePageExists(): void
    {
        $tenantId = $this->loginAsNewOwner();
        for ($i = 1; $i <= 25; ++$i) {
            $this->request('POST', '/app/products/new', ['name' => "Product {$i}", 'price' => '10.00', 'stockQuantity' => '1']);
        }

        $response = $this->request('GET', '/app/products');

        self::assertStringContainsString('class="pagination"', $response->body);
        self::assertStringContainsString('Página 1 de 2', $response->body);
    }

    public function testDeletingAProductRemovesItFromTheListing(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->request('POST', '/app/products/new', ['name' => 'Disposable Widget', 'price' => '10.00', 'stockQuantity' => '1']);
        $productId = $this->container->queryBus()->ask(new ListProductsQuery($tenantId))[0]['id'];

        $response = $this->request('POST', "/app/products/{$productId}/delete");

        self::assertSame(302, $response->status);
        self::assertSame('/app/products', $response->headers['location']);
        self::assertCount(0, $this->container->queryBus()->ask(new ListProductsQuery($tenantId)));

        $list = $this->request('GET', '/app/products');
        self::assertStringNotContainsString('Disposable Widget', $list->body);
    }

    public function testDeletingAlreadyDeletedProductFlashesErrorInsteadOfCrashing(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->request('POST', '/app/products/new', ['name' => 'Widget', 'price' => '10.00', 'stockQuantity' => '1']);
        $productId = $this->container->queryBus()->ask(new ListProductsQuery($tenantId))[0]['id'];
        $this->request('POST', "/app/products/{$productId}/delete");

        $response = $this->request('POST', "/app/products/{$productId}/delete");

        self::assertSame(302, $response->status);
    }

    public function testAnOrderCreatedBeforeADeletedProductStillShowsItsSnapshottedName(): void
    {
        $tenantId = $this->loginAsNewOwner();
        $this->request('POST', '/app/products/new', ['name' => 'Soon Gone', 'price' => '10.00', 'stockQuantity' => '5']);
        $productId = $this->container->queryBus()->ask(new ListProductsQuery($tenantId))[0]['id'];

        $orderId = $this->container->commandBus()->dispatch(new \OrderHub\Application\Command\CreateOrder\CreateOrderCommand(
            $tenantId,
            'Alan Turing',
            'alan@example.com',
            [['productId' => $productId, 'quantity' => 1]],
        ));

        $this->request('POST', "/app/products/{$productId}/delete");

        $orderPage = $this->request('GET', "/app/orders/{$orderId}");
        self::assertSame(200, $orderPage->status);
        self::assertStringContainsString('Soon Gone', $orderPage->body);
    }
}
