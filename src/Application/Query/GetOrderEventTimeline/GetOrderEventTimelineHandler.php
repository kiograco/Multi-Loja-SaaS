<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetOrderEventTimeline;

use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Domain\Shared\EventStoreInterface;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Reads the raw, chronologically ordered events of a single order straight from
 * the event store — unlike every other order query, which reads the
 * order_summary projection. This is the one place in the app that shows the
 * Event Sourcing history as-is, so the Web UI can render it as a timeline.
 */
final class GetOrderEventTimelineHandler
{
    public function __construct(
        private readonly OrderSummaryReadStore $summaries,
        private readonly EventStoreInterface $eventStore,
    ) {
    }

    /**
     * @return list<array{type: string, occurredAt: string, payload: array<string, mixed>}>
     */
    public function __invoke(GetOrderEventTimelineQuery $query): array
    {
        // Tenant-scoped read: an order of another tenant is simply "not found".
        if ($this->summaries->findForTenant($query->tenantId, $query->orderId) === null) {
            throw AggregateNotFoundException::order($query->orderId);
        }

        $timeline = [];
        foreach ($this->eventStore->load($query->orderId) as $event) {
            $timeline[] = [
                'type' => $event->eventType(),
                'occurredAt' => $event->occurredAt()->format(\DateTimeImmutable::ATOM),
                'payload' => $event->toPayload(),
            ];
        }

        return $timeline;
    }
}
