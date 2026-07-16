<?php

declare(strict_types=1);

namespace OrderHub\Application\Order;

use OrderHub\Application\EventBus\EventBus;
use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Shared\EventStoreInterface;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Application-side repository for the event-sourced Order.
 *
 * `load()` rebuilds the aggregate purely from its event stream. `save()` appends
 * the events the aggregate recorded (guarded by its version for optimistic
 * concurrency) and then hands those same events to the event bus so projectors
 * and side-effect dispatchers can react. Callers never see the event store.
 */
final class OrderRepository
{
    public function __construct(
        private readonly EventStoreInterface $eventStore,
        private readonly EventBus $eventBus,
    ) {
    }

    public function get(OrderId $id): Order
    {
        $stream = $this->eventStore->load($id->value);
        if ($stream->isEmpty()) {
            throw AggregateNotFoundException::order($id->value);
        }

        return Order::reconstituteFrom($stream);
    }

    public function find(OrderId $id): ?Order
    {
        $stream = $this->eventStore->load($id->value);

        return $stream->isEmpty() ? null : Order::reconstituteFrom($stream);
    }

    public function save(Order $order): void
    {
        $newEvents = $order->pullRecordedEvents();
        if ($newEvents === []) {
            return;
        }

        $expectedVersion = $order->version() - \count($newEvents);
        $this->eventStore->append($order->id()->value, $order->tenantId(), $newEvents, $expectedVersion);

        $this->eventBus->publish(...$newEvents);
    }
}
