<?php

declare(strict_types=1);

namespace OrderHub\Tests\Unit\Domain\Product;

use OrderHub\Domain\Product\Exceptions\InsufficientStockException;
use OrderHub\Domain\Product\Exceptions\InvalidProductException;
use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProductTest extends TestCase
{
    private function product(int $stock = 10): Product
    {
        return new Product(
            ProductId::generate(),
            Uuid::uuid4()->toString(),
            'Keyboard',
            Money::ofCents(9900),
            $stock,
        );
    }

    public function testRejectsBlankName(): void
    {
        $this->expectException(InvalidProductException::class);
        new Product(ProductId::generate(), Uuid::uuid4()->toString(), '   ', Money::ofCents(100), 1);
    }

    public function testRejectsNegativeStock(): void
    {
        $this->expectException(InvalidProductException::class);
        $this->product()->changeStock(-5);
    }

    public function testDecrementStockReducesQuantity(): void
    {
        $product = $this->product(10);
        $product->decrementStock(3);
        self::assertSame(7, $product->stockQuantity());
    }

    public function testDecrementBeyondStockThrows(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->product(2)->decrementStock(5);
    }
}
