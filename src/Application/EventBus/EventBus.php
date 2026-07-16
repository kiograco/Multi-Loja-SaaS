<?php

declare(strict_types=1);

namespace OrderHub\Application\EventBus;

use OrderHub\Domain\Shared\DomainEvent;

/**
 * Dispatches committed domain events to their subscribers in registration order.
 *
 * Subscribers are expected to be either projectors (which update read models
 * synchronously so a read after a write is never stale) or the side-effect
 * dispatcher (which only pushes jobs onto the queue — the slow work happens in
 * the worker). Because every subscriber here is fast, running them inline keeps
 * the write path simple without hurting latency.
 */
final class EventBus
{
    /** @var list<EventSubscriber> */
    private array $subscribers = [];

    public function subscribe(EventSubscriber $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            foreach ($this->subscribers as $subscriber) {
                $subscriber->on($event);
            }
        }
    }
}
