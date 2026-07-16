<?php

declare(strict_types=1);

namespace OrderHub\Application\Bus;

/**
 * Marker interface for read-side messages (CQRS query). Queries never change
 * state and are answered from projections (read models), never from the event
 * store, keeping reads fast and writes uncontended.
 */
interface Query
{
}
