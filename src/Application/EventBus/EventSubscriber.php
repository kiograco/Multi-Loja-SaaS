<?php

declare(strict_types=1);

namespace OrderHub\Application\EventBus;

use OrderHub\Domain\Shared\DomainEvent;

/**
 * Reacts to domain events after they have been committed to the event store.
 * Projectors (which keep read models up to date) and the async side-effect
 * dispatcher both implement this.
 */
interface EventSubscriber
{
    public function on(DomainEvent $event): void;
}
