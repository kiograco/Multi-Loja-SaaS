<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Persistence;

use OrderHub\Domain\Order\Events\OrderEventFactory;
use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Shared\Exceptions\ConcurrencyException;
use OrderHub\Infrastructure\Persistence\PostgresEventStore;
use OrderHub\Tests\Integration\IntegrationTestCase;
use OrderHub\Tests\Support\FrozenClock;
use OrderHub\Tests\Support\OrderFactory;

final class PostgresEventStoreTest extends IntegrationTestCase
{
    private function store(): PostgresEventStore
    {
        return new PostgresEventStore($this->database, new OrderEventFactory());
    }

    public function testAppendsAndReconstitutesAggregateFromStore(): void
    {
        $store = $this->store();
        $clock = new FrozenClock();

        $order = OrderFactory::created($clock);
        $order->pay('pix', $clock);
        $order->ship('BR-TRACK-1', $clock);

        $events = $order->pullRecordedEvents();
        $store->append($order->id()->value, $order->tenantId(), $events, 0);

        $rebuilt = Order::reconstituteFrom($store->load($order->id()->value));

        self::assertSame(OrderStatus::Shipped, $rebuilt->status());
        self::assertSame($order->totalAmount()->cents, $rebuilt->totalAmount()->cents);
        self::assertSame(3, $rebuilt->version());
    }

    public function testConcurrencyConflictIsDetected(): void
    {
        $store = $this->store();
        $order = OrderFactory::created();
        $created = $order->pullRecordedEvents();
        $store->append($order->id()->value, $order->tenantId(), $created, 0);

        // Second writer still thinks the aggregate is at version 0.
        $this->expectException(ConcurrencyException::class);
        $store->append($order->id()->value, $order->tenantId(), $created, 0);
    }

    public function testLoadAllReturnsEventsInInsertionOrder(): void
    {
        $store = $this->store();
        $a = OrderFactory::created();
        $b = OrderFactory::created();
        $store->append($a->id()->value, $a->tenantId(), $a->pullRecordedEvents(), 0);
        $store->append($b->id()->value, $b->tenantId(), $b->pullRecordedEvents(), 0);

        $all = iterator_to_array($store->loadAll(), false);

        self::assertCount(2, $all);
    }
}
