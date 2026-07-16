<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An ordered, immutable sequence of domain events belonging to a single aggregate.
 *
 * @implements IteratorAggregate<int, DomainEvent>
 */
final class EventStream implements IteratorAggregate, Countable
{
    /** @var list<DomainEvent> */
    private array $events;

    public function __construct(DomainEvent ...$events)
    {
        $this->events = array_values($events);
    }

    /**
     * @param iterable<DomainEvent> $events
     */
    public static function fromIterable(iterable $events): self
    {
        $stream = new self();
        foreach ($events as $event) {
            $stream->events[] = $event;
        }

        return $stream;
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    /**
     * @return list<DomainEvent>
     */
    public function toArray(): array
    {
        return $this->events;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }

    public function count(): int
    {
        return \count($this->events);
    }
}
