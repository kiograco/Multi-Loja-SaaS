<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order;

use OrderHub\Domain\Order\Exceptions\InvalidOrderException;
use OrderHub\Domain\Shared\Money;

/**
 * A single line of an order. Immutable value object: the unit price is captured
 * at order time so later product price changes never rewrite order history.
 */
final readonly class OrderItem
{
    public function __construct(
        public string $productId,
        public string $productName,
        public Money $unitPrice,
        public int $quantity,
    ) {
        if ($quantity < 1) {
            throw InvalidOrderException::nonPositiveQuantity($productId, $quantity);
        }
    }

    public function lineTotal(): Money
    {
        return $this->unitPrice->multipliedBy($this->quantity);
    }

    /**
     * @return array{productId: string, productName: string, unitPriceCents: int, currency: string, quantity: int}
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'productName' => $this->productName,
            'unitPriceCents' => $this->unitPrice->cents,
            'currency' => $this->unitPrice->currency,
            'quantity' => $this->quantity,
        ];
    }

    /**
     * @param array{productId: string, productName: string, unitPriceCents: int, currency: string, quantity: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['productId'],
            $data['productName'],
            Money::ofCents($data['unitPriceCents'], $data['currency']),
            $data['quantity'],
        );
    }
}
