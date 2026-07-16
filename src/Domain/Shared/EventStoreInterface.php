<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

/**
 * Port for the append-only event store. The domain depends on this interface;
 * concrete adapters (Postgres, in-memory) live in the infrastructure/test layers.
 */
interface EventStoreInterface
{
    /**
     * Append new events for an aggregate, guarding against concurrent writes.
     *
     * @param list<DomainEvent> $events
     *
     * @throws \OrderHub\Domain\Shared\Exceptions\ConcurrencyException when the
     *                                                                 stored version no longer matches $expectedVersion
     */
    public function append(string $aggregateId, string $tenantId, array $events, int $expectedVersion): void;

    /**
     * Load the full ordered event stream for a single aggregate.
     */
    public function load(string $aggregateId): EventStream;

    /**
     * Stream every stored event in global insertion order. Used by projection
     * rebuilds, which must replay the whole history deterministically.
     *
     * @return iterable<DomainEvent>
     */
    public function loadAll(): iterable;
}
