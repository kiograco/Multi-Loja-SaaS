<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Http;

use Redis;

/**
 * Fixed-window rate limiter backed by Redis. One counter per tenant per minute:
 * the first request in a window sets a 60s TTL, subsequent ones INCR it, and the
 * window resets automatically when the key expires. Simple and adequate for the
 * project's "100 req/min per tenant" requirement.
 */
final class RateLimiter
{
    public function __construct(
        private readonly Redis $redis,
        private readonly int $maxPerMinute,
    ) {
    }

    /**
     * @return array{allowed: bool, remaining: int, limit: int}
     */
    public function hit(string $tenantId): array
    {
        $window = (int) floor(time() / 60);
        $key = \sprintf('orderhub:ratelimit:%s:%d', $tenantId, $window);

        $count = (int) $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, 60);
        }

        $remaining = max(0, $this->maxPerMinute - $count);

        return [
            'allowed' => $count <= $this->maxPerMinute,
            'remaining' => $remaining,
            'limit' => $this->maxPerMinute,
        ];
    }
}
