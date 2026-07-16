<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

use DateTimeImmutable;

/**
 * A domain event is an immutable fact that already happened.
 * Events are the source of truth for event-sourced aggregates.
 */
interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredAt(): DateTimeImmutable;

    /**
     * Stable, storage-facing name of the event (e.g. "OrderCreated").
     * Used as the discriminator column in the event store.
     */
    public function eventType(): string;

    /**
     * Serialize the event body to a JSON-friendly associative array.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * Rebuild the event from its persisted payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self;
}
