<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order;

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
use OrderHub\Domain\Shared\AggregateRoot;
use OrderHub\Domain\Shared\Clock;
use OrderHub\Domain\Shared\DomainEvent;
use OrderHub\Domain\Shared\EventStream;
use OrderHub\Domain\Shared\Money;

/**
 * Event-sourced Order aggregate.
 *
 * All state is derived from the event history: `create()` and the lifecycle
 * methods enforce invariants and then record events; `when()` is the single
 * mutator, shared by new events and by replay. The aggregate never touches
 * persistence — the application layer pulls its recorded events and appends
 * them to the event store.
 */
final class Order extends AggregateRoot
{
    private OrderId $id;
    private string $tenantId;
    private string $customerName;
    private string $customerEmail;
    /** @var list<OrderItem> */
    private array $items = [];
    private Money $totalAmount;
    private OrderStatus $status;

    private function __construct()
    {
        // Aggregates are only built via create() or reconstituteFrom().
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function create(
        OrderId $id,
        string $tenantId,
        string $customerName,
        string $customerEmail,
        array $items,
        Clock $clock,
    ): self {
        $customerName = trim($customerName);
        if ($customerName === '') {
            throw InvalidOrderException::blankCustomerName();
        }
        if (!filter_var($customerEmail, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidOrderException::invalidCustomerEmail($customerEmail);
        }
        if ($items === []) {
            throw InvalidOrderException::emptyItems();
        }

        $total = Money::zero();
        foreach ($items as $item) {
            $total = $total->add($item->lineTotal());
        }

        $order = new self();
        $order->recordThat(new OrderCreated(
            $id->value,
            $tenantId,
            $customerName,
            $customerEmail,
            array_values($items),
            $total,
            $clock->now(),
        ));

        return $order;
    }

    public static function reconstituteFrom(EventStream $events): self
    {
        $order = new self();
        $order->replayStream($events);

        return $order;
    }

    public function pay(string $paymentMethod, Clock $clock): void
    {
        if ($this->status !== OrderStatus::Created) {
            throw OrderCannotBePaidException::inStatus($this->status);
        }

        $this->recordThat(new PaymentReceived(
            $this->id->value,
            $paymentMethod,
            $this->totalAmount,
            $clock->now(),
        ));
    }

    public function ship(string $trackingCode, Clock $clock): void
    {
        if ($this->status !== OrderStatus::Paid) {
            throw OrderCannotBeShippedException::inStatus($this->status);
        }

        $this->recordThat(new OrderShipped($this->id->value, $trackingCode, $clock->now()));
    }

    public function deliver(Clock $clock): void
    {
        if ($this->status !== OrderStatus::Shipped) {
            throw OrderCannotBeDeliveredException::inStatus($this->status);
        }

        $this->recordThat(new OrderDelivered($this->id->value, $clock->now()));
    }

    public function cancel(string $reason, Clock $clock): void
    {
        if (\in_array($this->status, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            throw OrderCannotBeCancelledException::inStatus($this->status);
        }

        $this->recordThat(new OrderCancelled($this->id->value, $reason, $clock->now()));
    }

    protected function when(DomainEvent $event): void
    {
        match (true) {
            $event instanceof OrderCreated => $this->whenOrderCreated($event),
            $event instanceof PaymentReceived => $this->status = OrderStatus::Paid,
            $event instanceof OrderShipped => $this->status = OrderStatus::Shipped,
            $event instanceof OrderDelivered => $this->status = OrderStatus::Delivered,
            $event instanceof OrderCancelled => $this->status = OrderStatus::Cancelled,
            default => null,
        };
    }

    private function whenOrderCreated(OrderCreated $event): void
    {
        $this->id = OrderId::fromString($event->orderId);
        $this->tenantId = $event->tenantId;
        $this->customerName = $event->customerName;
        $this->customerEmail = $event->customerEmail;
        $this->items = $event->items;
        $this->totalAmount = $event->totalAmount;
        $this->status = OrderStatus::Created;
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function customerEmail(): string
    {
        return $this->customerEmail;
    }

    /**
     * @return list<OrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }
}
