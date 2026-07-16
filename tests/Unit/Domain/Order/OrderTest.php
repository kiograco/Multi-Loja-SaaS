<?php

declare(strict_types=1);

namespace OrderHub\Tests\Unit\Domain\Order;

use OrderHub\Domain\Order\Events\OrderCancelled;
use OrderHub\Domain\Order\Events\OrderCreated;
use OrderHub\Domain\Order\Events\OrderDelivered;
use OrderHub\Domain\Order\Events\OrderShipped;
use OrderHub\Domain\Order\Events\PaymentReceived;
use OrderHub\Domain\Order\Exceptions\InvalidOrderException;
use OrderHub\Domain\Order\Exceptions\OrderCannotBeCancelledException;
use OrderHub\Domain\Order\Exceptions\OrderCannotBeDeliveredException;
use OrderHub\Domain\Order\Exceptions\OrderCannotBePaidException;
use OrderHub\Domain\Order\Exceptions\OrderCannotBeShippedException;
use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Shared\EventStream;
use OrderHub\Domain\Shared\Money;
use OrderHub\Tests\Support\FrozenClock;
use OrderHub\Tests\Support\OrderFactory;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class OrderTest extends TestCase
{
    public function testCreatingAnOrderRecordsOrderCreatedAndComputesTotal(): void
    {
        $order = OrderFactory::created();

        $events = $order->pullRecordedEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderCreated::class, $events[0]);
        self::assertSame(OrderStatus::Created, $order->status());
        // 1 x 1500.00 + 4 x 25.00 = 1600.00
        self::assertSame(160000, $order->totalAmount()->cents);
    }

    public function testOrderRequiresAtLeastOneItem(): void
    {
        $this->expectException(InvalidOrderException::class);

        Order::create(
            OrderId::generate(),
            Uuid::uuid4()->toString(),
            'Grace Hopper',
            'grace@example.com',
            [],
            new FrozenClock(),
        );
    }

    public function testOrderRejectsInvalidCustomerEmail(): void
    {
        $this->expectException(InvalidOrderException::class);

        Order::create(
            OrderId::generate(),
            Uuid::uuid4()->toString(),
            'Grace Hopper',
            'not-an-email',
            [new OrderItem(Uuid::uuid4()->toString(), 'Widget', Money::ofCents(1000), 1)],
            new FrozenClock(),
        );
    }

    public function testPayingAnOrderTransitionsToPaid(): void
    {
        $order = OrderFactory::created();
        $order->pullRecordedEvents();

        $order->pay('credit_card', new FrozenClock());

        $events = $order->pullRecordedEvents();
        self::assertInstanceOf(PaymentReceived::class, $events[0]);
        self::assertSame(OrderStatus::Paid, $order->status());
    }

    public function testAnOrderCannotBePaidTwice(): void
    {
        $order = OrderFactory::created();
        $order->pay('pix', new FrozenClock());

        $this->expectException(OrderCannotBePaidException::class);
        $order->pay('pix', new FrozenClock());
    }

    public function testAnOrderCannotBeShippedBeforePayment(): void
    {
        $order = OrderFactory::created();

        $this->expectException(OrderCannotBeShippedException::class);
        $order->ship('BR123', new FrozenClock());
    }

    public function testShippingAPaidOrderTransitionsToShipped(): void
    {
        $order = OrderFactory::created();
        $order->pay('pix', new FrozenClock());

        $order->ship('BR123456789', new FrozenClock());

        self::assertSame(OrderStatus::Shipped, $order->status());
        $events = $order->pullRecordedEvents();
        self::assertInstanceOf(OrderShipped::class, $events[array_key_last($events)]);
    }

    public function testDeliveringRequiresShipped(): void
    {
        $order = OrderFactory::created();
        $order->pay('pix', new FrozenClock());

        $this->expectException(OrderCannotBeDeliveredException::class);
        $order->deliver(new FrozenClock());
    }

    public function testDeliveringAShippedOrderTransitionsToDelivered(): void
    {
        $order = OrderFactory::created();
        $order->pay('pix', new FrozenClock());
        $order->ship('BR1', new FrozenClock());

        $order->deliver(new FrozenClock());

        self::assertSame(OrderStatus::Delivered, $order->status());
        $events = $order->pullRecordedEvents();
        self::assertInstanceOf(OrderDelivered::class, $events[array_key_last($events)]);
    }

    public function testAnOrderCanBeCancelledBeforeShipping(): void
    {
        $order = OrderFactory::created();

        $order->cancel('customer changed mind', new FrozenClock());

        self::assertSame(OrderStatus::Cancelled, $order->status());
        $events = $order->pullRecordedEvents();
        self::assertInstanceOf(OrderCancelled::class, $events[array_key_last($events)]);
    }

    public function testAPaidOrderCanStillBeCancelled(): void
    {
        $order = OrderFactory::created();
        $order->pay('pix', new FrozenClock());

        $order->cancel('fraud suspected', new FrozenClock());

        self::assertSame(OrderStatus::Cancelled, $order->status());
    }

    public function testAShippedOrderCanNoLongerBeCancelled(): void
    {
        $order = OrderFactory::created();
        $order->pay('pix', new FrozenClock());
        $order->ship('BR1', new FrozenClock());

        $this->expectException(OrderCannotBeCancelledException::class);
        $order->cancel('too late', new FrozenClock());
    }

    public function testReconstituteFromRebuildsStateFromHistory(): void
    {
        $original = OrderFactory::created();
        $original->pay('pix', new FrozenClock());
        $original->ship('BR999', new FrozenClock());
        $history = $original->pullRecordedEvents();

        $rebuilt = Order::reconstituteFrom(new EventStream(...$history));

        self::assertSame(OrderStatus::Shipped, $rebuilt->status());
        self::assertSame($original->totalAmount()->cents, $rebuilt->totalAmount()->cents);
        self::assertCount(3, $history);
        self::assertSame(3, $rebuilt->version());
        // A reconstituted aggregate has no pending events to persist.
        self::assertCount(0, $rebuilt->pullRecordedEvents());
    }
}
