<?php

declare(strict_types=1);

namespace OrderHub\Application\Bus;

/**
 * Marker interface for write-side messages (CQRS command). Commands express
 * intent to change state and are named in the imperative (CreateOrderCommand).
 */
interface Command
{
}
