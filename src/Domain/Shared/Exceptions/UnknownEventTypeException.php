<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared\Exceptions;

final class UnknownEventTypeException extends DomainException
{
    public static function forType(string $eventType): self
    {
        return new self(\sprintf('No domain event is registered for type "%s".', $eventType));
    }
}
