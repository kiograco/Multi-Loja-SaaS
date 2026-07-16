<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Events;

use DateTimeImmutable;
use OrderHub\Domain\Shared\DomainEvent;

final readonly class OrderDelivered implements DomainEvent
{
    public function __construct(
        public string $orderId,
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
        return 'OrderDelivered';
    }

    public function toPayload(): array
    {
        return [
            'orderId' => $this->orderId,
            'occurredAt' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            (string) $payload['orderId'],
            new DateTimeImmutable((string) $payload['occurredAt']),
        );
    }
}
