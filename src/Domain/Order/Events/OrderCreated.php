<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Events;

use DateTimeImmutable;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Domain\Shared\DomainEvent;
use OrderHub\Domain\Shared\Money;

final readonly class OrderCreated implements DomainEvent
{
    /**
     * @param list<OrderItem> $items
     */
    public function __construct(
        public string $orderId,
        public string $tenantId,
        public string $customerName,
        public string $customerEmail,
        public array $items,
        public Money $totalAmount,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->orderId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventType(): string
    {
        return 'OrderCreated';
    }

    public function toPayload(): array
    {
        return [
            'orderId' => $this->orderId,
            'tenantId' => $this->tenantId,
            'customerName' => $this->customerName,
            'customerEmail' => $this->customerEmail,
            'items' => array_map(static fn (OrderItem $i): array => $i->toArray(), $this->items),
            'totalAmountCents' => $this->totalAmount->cents,
            'currency' => $this->totalAmount->currency,
            'occurredAt' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        /** @var list<array{productId: string, productName: string, unitPriceCents: int, currency: string, quantity: int}> $rawItems */
        $rawItems = $payload['items'];

        return new self(
            (string) $payload['orderId'],
            (string) $payload['tenantId'],
            (string) $payload['customerName'],
            (string) $payload['customerEmail'],
            array_map(static fn (array $i): OrderItem => OrderItem::fromArray($i), $rawItems),
            Money::ofCents((int) $payload['totalAmountCents'], (string) $payload['currency']),
            new DateTimeImmutable((string) $payload['occurredAt']),
        );
    }
}
