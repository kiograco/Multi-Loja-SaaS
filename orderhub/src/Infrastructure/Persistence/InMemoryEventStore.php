<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use OrderHub\Domain\Shared\DomainEvent;
use OrderHub\Domain\Shared\EventStoreInterface;
use OrderHub\Domain\Shared\EventStream;
use OrderHub\Domain\Shared\Exceptions\ConcurrencyException;

/**
 * In-memory event store. Behaves like the Postgres adapter (including optimistic
 * concurrency) but without a database, so application-layer handlers can be
 * exercised in fast unit tests.
 */
final class InMemoryEventStore implements EventStoreInterface
{
    /** @var array<string, list<DomainEvent>> keyed by aggregate id */
    private array $streams = [];

    /** @var list<DomainEvent> global insertion order */
    private array $all = [];

    public function append(string $aggregateId, string $tenantId, array $events, int $expectedVersion): void
    {
        $current = \count($this->streams[$aggregateId] ?? []);
        if ($current !== $expectedVersion) {
            throw ConcurrencyException::versionMismatch($aggregateId, $expectedVersion, $current);
        }

        foreach ($events as $event) {
            $this->streams[$aggregateId][] = $event;
            $this->all[] = $event;
        }
    }

    public function load(string $aggregateId): EventStream
    {
        return new EventStream(...($this->streams[$aggregateId] ?? []));
    }

    public function loadAll(): iterable
    {
        yield from $this->all;
    }
}
