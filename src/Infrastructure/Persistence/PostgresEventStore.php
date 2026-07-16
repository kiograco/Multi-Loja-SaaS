<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use OrderHub\Domain\Order\Events\OrderEventFactory;
use OrderHub\Domain\Shared\DomainEvent;
use OrderHub\Domain\Shared\EventStoreInterface;
use OrderHub\Domain\Shared\EventStream;
use OrderHub\Domain\Shared\Exceptions\ConcurrencyException;
use PDO;

/**
 * PostgreSQL adapter for the append-only event store.
 *
 * Concurrency control relies on the UNIQUE (aggregate_id, version) constraint:
 * each event is written with a monotonically increasing version derived from
 * $expectedVersion. Two racing writers computing the same version collide on
 * the constraint, and we surface that as a ConcurrencyException.
 */
final class PostgresEventStore implements EventStoreInterface
{
    public function __construct(
        private readonly Database $database,
        private readonly OrderEventFactory $eventFactory,
    ) {
    }

    public function append(string $aggregateId, string $tenantId, array $events, int $expectedVersion): void
    {
        if ($events === []) {
            return;
        }

        $pdo = $this->database->pdo();
        $pdo->beginTransaction();
        try {
            $currentVersion = $this->currentVersion($pdo, $aggregateId);
            if ($currentVersion !== $expectedVersion) {
                throw ConcurrencyException::versionMismatch($aggregateId, $expectedVersion, $currentVersion);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO event_store (aggregate_id, tenant_id, event_type, payload, occurred_at, version)
                 VALUES (:aggregate_id, :tenant_id, :event_type, :payload, :occurred_at, :version)'
            );

            $version = $expectedVersion;
            foreach ($events as $event) {
                ++$version;
                $stmt->execute([
                    'aggregate_id' => $aggregateId,
                    'tenant_id' => $tenantId,
                    'event_type' => $event->eventType(),
                    'payload' => json_encode($event->toPayload(), \JSON_THROW_ON_ERROR),
                    'occurred_at' => $event->occurredAt()->format(\DateTimeImmutable::ATOM),
                    'version' => $version,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof ConcurrencyException) {
                throw $e;
            }
            // A unique-violation here means another writer won the race.
            if ($e instanceof \PDOException && $e->getCode() === '23505') {
                throw ConcurrencyException::versionMismatch($aggregateId, $expectedVersion, $expectedVersion);
            }
            throw $e;
        }
    }

    public function load(string $aggregateId): EventStream
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT event_type, payload FROM event_store
             WHERE aggregate_id = :aggregate_id ORDER BY version ASC'
        );
        $stmt->execute(['aggregate_id' => $aggregateId]);

        $events = [];
        /** @var array{event_type: string, payload: string} $row */
        foreach ($stmt as $row) {
            $events[] = $this->hydrate($row['event_type'], $row['payload']);
        }

        return new EventStream(...$events);
    }

    public function loadAll(): iterable
    {
        $stmt = $this->database->pdo()->query(
            'SELECT event_type, payload FROM event_store ORDER BY id ASC'
        );
        if ($stmt === false) {
            return;
        }

        /** @var array{event_type: string, payload: string} $row */
        foreach ($stmt as $row) {
            yield $this->hydrate($row['event_type'], $row['payload']);
        }
    }

    private function currentVersion(PDO $pdo, string $aggregateId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(version), 0) AS v FROM event_store WHERE aggregate_id = :id'
        );
        $stmt->execute(['id' => $aggregateId]);

        return (int) $stmt->fetchColumn();
    }

    private function hydrate(string $eventType, string $payloadJson): DomainEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($payloadJson, true, 512, \JSON_THROW_ON_ERROR);

        return $this->eventFactory->reconstitute($eventType, $payload);
    }
}
