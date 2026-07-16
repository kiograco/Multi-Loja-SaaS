<?php

declare(strict_types=1);

namespace OrderHub\Application\Queue;

/**
 * A unit of asynchronous work. `jobId` is the idempotency key: reprocessing a
 * job with the same id must not duplicate its effect (enforced via the
 * processed_jobs ledger). `attempts` drives retry/backoff decisions.
 */
final readonly class QueuedJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $jobId,
        public string $type,
        public array $payload,
        public int $attempts = 0,
    ) {
    }

    public function nextAttempt(): self
    {
        return new self($this->jobId, $this->type, $this->payload, $this->attempts + 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'type' => $this->type,
            'payload' => $this->payload,
            'attempts' => $this->attempts,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $payload */
        $payload = $data['payload'] ?? [];

        return new self(
            (string) $data['jobId'],
            (string) $data['type'],
            $payload,
            (int) ($data['attempts'] ?? 0),
        );
    }
}
