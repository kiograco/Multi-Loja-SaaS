<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Exceptions;

use OrderHub\Domain\Shared\Exceptions\DomainException;

final class InvalidOrderException extends DomainException
{
    public static function emptyItems(): self
    {
        return new self('An order must contain at least one item.');
    }

    public static function nonPositiveQuantity(string $productId, int $quantity): self
    {
        return new self(\sprintf('Item quantity for product %s must be >= 1, got %d.', $productId, $quantity));
    }

    public static function blankCustomerName(): self
    {
        return new self('Customer name cannot be blank.');
    }

    public static function invalidCustomerEmail(string $email): self
    {
        return new self(\sprintf('"%s" is not a valid customer e-mail.', $email));
    }
}
