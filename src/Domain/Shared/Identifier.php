<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

use OrderHub\Domain\Shared\Exceptions\InvalidUuidException;
use Ramsey\Uuid\Uuid;

/**
 * Base UUID-backed identifier value object. Concrete ids (OrderId, ProductId,
 * TenantId) extend this to gain type safety without duplicating validation.
 */
abstract readonly class Identifier
{
    final public function __construct(public string $value)
    {
        if (!Uuid::isValid($value)) {
            throw InvalidUuidException::forValue($value);
        }
    }

    public static function generate(): static
    {
        return new static(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function equals(self $other): bool
    {
        return $this::class === $other::class && $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
