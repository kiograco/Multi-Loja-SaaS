<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Events;

use DateTimeImmutable;
use OrderHub\Domain\Shared\DomainEvent;

final readonly class OrderShipped implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $trackingCode,
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
        return 'OrderShipped';
    }

    public function toPayload(): array
    {
        return [
            'orderId' => $this->orderId,
            'trackingCode' => $this->trackingCode,
            'occurredAt' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            (string) $payload['orderId'],
            (string) $payload['trackingCode'],
            new DateTimeImmutable((string) $payload['occurredAt']),
        );
    }
}
