<?php

declare(strict_types=1);

namespace OrderHub\Domain\Product;

use OrderHub\Domain\Product\Exceptions\InsufficientStockException;
use OrderHub\Domain\Product\Exceptions\InvalidProductException;
use OrderHub\Domain\Shared\Money;

/**
 * Traditional CRUD aggregate with mutable state — intentionally NOT event
 * sourced. Product history has no business/audit value the way order history
 * does, so the extra machinery of event sourcing would be cost without payoff.
 * (See README, "Decisões de Arquitetura".)
 */
final class Product
{
    private string $name;
    private Money $price;
    private int $stockQuantity;

    public function __construct(
        public readonly ProductId $id,
        public readonly string $tenantId,
        string $name,
        Money $price,
        int $stockQuantity,
    ) {
        $this->rename($name);
        $this->price = $price;
        $this->changeStock($stockQuantity);
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw InvalidProductException::blankName();
        }
        $this->name = $name;
    }

    public function changePrice(Money $price): void
    {
        $this->price = $price;
    }

    public function changeStock(int $stockQuantity): void
    {
        if ($stockQuantity < 0) {
            throw InvalidProductException::negativeStock($stockQuantity);
        }
        $this->stockQuantity = $stockQuantity;
    }

    public function decrementStock(int $quantity): void
    {
        if ($quantity > $this->stockQuantity) {
            throw InsufficientStockException::forProduct($this->id->value, $this->stockQuantity, $quantity);
        }
        $this->stockQuantity -= $quantity;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function stockQuantity(): int
    {
        return $this->stockQuantity;
    }
}
