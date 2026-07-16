<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared\Exceptions;

final class ConcurrencyException extends DomainException
{
    public static function versionMismatch(string $aggregateId, int $expected, int $actual): self
    {
        return new self(\sprintf(
            'Optimistic concurrency conflict on aggregate %s: expected version %d but store is at %d.',
            $aggregateId,
            $expected,
            $actual,
        ));
    }
}
