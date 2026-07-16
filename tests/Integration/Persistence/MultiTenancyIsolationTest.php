<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Persistence;

use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Shared\Money;
use OrderHub\Infrastructure\Persistence\PostgresProductRepository;
use OrderHub\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

final class MultiTenancyIsolationTest extends IntegrationTestCase
{
    public function testATenantCannotReadAnotherTenantsProduct(): void
    {
        $repo = new PostgresProductRepository($this->database);

        $tenantA = Uuid::uuid4()->toString();
        $tenantB = Uuid::uuid4()->toString();

        $product = new Product(ProductId::generate(), $tenantA, 'Secret Widget', Money::ofCents(5000), 3);
        $repo->save($product);

        // Same product id, but queried as tenant B — must be invisible.
        self::assertNotNull($repo->findById($tenantA, $product->id));
        self::assertNull($repo->findById($tenantB, $product->id));
        self::assertCount(1, $repo->findAllForTenant($tenantA));
        self::assertCount(0, $repo->findAllForTenant($tenantB));
    }

    public function testATenantCannotOverwriteAnotherTenantsProduct(): void
    {
        $repo = new PostgresProductRepository($this->database);
        $tenantA = Uuid::uuid4()->toString();
        $tenantB = Uuid::uuid4()->toString();

        $id = ProductId::generate();
        $repo->save(new Product($id, $tenantA, 'Owned by A', Money::ofCents(1000), 5));

        // Attempt a cross-tenant overwrite reusing the same id under tenant B.
        $repo->save(new Product($id, $tenantB, 'Hijacked by B', Money::ofCents(1), 999));

        $stillA = $repo->findById($tenantA, $id);
        self::assertNotNull($stillA);
        self::assertSame('Owned by A', $stillA->name());
        self::assertSame(5, $stillA->stockQuantity());
        // The upsert's tenant guard prevents B from ever owning the row.
        self::assertNull($repo->findById($tenantB, $id));
    }
}
