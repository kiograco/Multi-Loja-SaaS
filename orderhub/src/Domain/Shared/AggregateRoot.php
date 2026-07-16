<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

/**
 * Base class for event-sourced aggregates.
 *
 * State transitions happen exclusively through domain events: `recordThat()`
 * for new events (which are collected for persistence) and `replayStream()`
 * for rebuilding state from history. Both funnel through `when()`, so there is
 * a single place where state mutates.
 */
abstract class AggregateRoot
{
    private int $version = 0;

    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * Mutate in-memory state in response to an event. Must not enforce
     * invariants (those are checked before recording) and must be total for
     * every event type the aggregate can emit.
     */
    abstract protected function when(DomainEvent $event): void;

    protected function recordThat(DomainEvent $event): void
    {
        $this->when($event);
        ++$this->version;
        $this->recordedEvents[] = $event;
    }

    final protected function replayStream(EventStream $stream): void
    {
        foreach ($stream as $event) {
            $this->when($event);
            ++$this->version;
        }
    }

    /**
     * Return and clear the events recorded since the last pull. The
     * application layer calls this to persist them in the event store.
     *
     * @return list<DomainEvent>
     */
    public function pullRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Current version = number of events applied. Used for optimistic
     * concurrency control when appending to the event store.
     */
    public function version(): int
    {
        return $this->version;
    }
}
