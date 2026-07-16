<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared\Exceptions;

final class AggregateNotFoundException extends DomainException
{
    public static function order(string $orderId): self
    {
        return new self(\sprintf('Order %s was not found.', $orderId));
    }

    public static function product(string $productId): self
    {
        return new self(\sprintf('Product %s was not found.', $productId));
    }

    public static function tenant(string $tenantId): self
    {
        return new self(\sprintf('Tenant %s was not found.', $tenantId));
    }
}
