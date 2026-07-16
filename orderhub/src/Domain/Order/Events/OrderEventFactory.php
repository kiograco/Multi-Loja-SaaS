<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Events;

use OrderHub\Domain\Shared\DomainEvent;
use OrderHub\Domain\Shared\Exceptions\UnknownEventTypeException;

/**
 * Maps a persisted event type + payload back to a concrete domain event.
 * Kept in the domain so the event store adapter stays free of knowledge about
 * individual event classes — it just hands over the type string and the JSON.
 */
final class OrderEventFactory
{
    /** @var array<string, class-string<DomainEvent>> */
    private const MAP = [
        'OrderCreated' => OrderCreated::class,
        'PaymentReceived' => PaymentReceived::class,
        'OrderShipped' => OrderShipped::class,
        'OrderDelivered' => OrderDelivered::class,
        'OrderCancelled' => OrderCancelled::class,
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function reconstitute(string $eventType, array $payload): DomainEvent
    {
        $class = self::MAP[$eventType] ?? throw UnknownEventTypeException::forType($eventType);

        return $class::fromPayload($payload);
    }

    public function supports(string $eventType): bool
    {
        return \array_key_exists($eventType, self::MAP);
    }
}
