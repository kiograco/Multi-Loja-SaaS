<?php

declare(strict_types=1);

namespace OrderHub\Domain\Product\Exceptions;

use OrderHub\Domain\Shared\Exceptions\DomainException;

final class InsufficientStockException extends DomainException
{
    public static function forProduct(string $productId, int $available, int $requested): self
    {
        return new self(\sprintf(
            'Product %s has only %d in stock but %d was requested.',
            $productId,
            $available,
            $requested,
        ));
    }
}
