<?php

declare(strict_types=1);

namespace OrderHub\Tests\Support;

use DateTimeImmutable;
use OrderHub\Domain\Shared\Clock;

/**
 * Deterministic clock for tests. Time only advances when the test asks it to.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $isoTime = '2026-01-01T12:00:00+00:00')
    {
        $this->now = new DateTimeImmutable($isoTime);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->now = $this->now->modify(\sprintf('+%d seconds', $seconds));
    }
}
