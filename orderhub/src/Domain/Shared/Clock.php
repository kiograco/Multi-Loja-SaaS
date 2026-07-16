<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

use DateTimeImmutable;

/**
 * Abstracts "now" so the domain never calls the system clock directly,
 * keeping time-dependent behaviour deterministic under test.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
