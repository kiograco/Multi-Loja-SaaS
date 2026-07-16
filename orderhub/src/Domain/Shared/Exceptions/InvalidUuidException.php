<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared\Exceptions;

final class InvalidUuidException extends DomainException
{
    public static function forValue(string $value): self
    {
        return new self(\sprintf('"%s" is not a valid UUID.', $value));
    }
}
