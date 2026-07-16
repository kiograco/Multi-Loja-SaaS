<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Events;

use DateTimeImmutable;
use OrderHub\Domain\Shared\DomainEvent;
use OrderHub\Domain\Shared\Money;

final readonly class PaymentReceived implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $paymentMethod,
        public Money $amountPaid,
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
        return 'PaymentReceived';
    }

    public function toPayload(): array
    {
        return [
            'orderId' => $this->orderId,
            'paymentMethod' => $this->paymentMethod,
            'amountPaidCents' => $this->amountPaid->cents,
            'currency' => $this->amountPaid->currency,
            'occurredAt' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            (string) $payload['orderId'],
            (string) $payload['paymentMethod'],
            Money::ofCents((int) $payload['amountPaidCents'], (string) $payload['currency']),
            new DateTimeImmutable((string) $payload['occurredAt']),
        );
    }
}
