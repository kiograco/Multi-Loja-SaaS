<?php

declare(strict_types=1);

namespace OrderHub\Domain\Product\Exceptions;

use OrderHub\Domain\Shared\Exceptions\DomainException;

final class InvalidProductException extends DomainException
{
    public static function blankName(): self
    {
        return new self('Product name cannot be blank.');
    }

    public static function negativeStock(int $quantity): self
    {
        return new self(\sprintf('Product stock cannot be negative, got %d.', $quantity));
    }
}
