<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Queue;

use OrderHub\Application\Queue\JobQueue;
use OrderHub\Application\Queue\QueuedJob;

/**
 * In-memory job queue for tests. Mirrors the Redis adapter's semantics
 * (ready / delayed / dead-letter) and exposes inspection helpers so tests can
 * assert on retry and DLQ behaviour without Redis.
 */
final class InMemoryJobQueue implements JobQueue
{
    /** @var list<QueuedJob> */
    private array $ready = [];
    /** @var list<array{job: QueuedJob, availableAt: int}> */
    private array $delayed = [];
    /** @var list<array{job: QueuedJob, error: string}> */
    private array $dead = [];

    private int $now = 0;

    public function enqueue(QueuedJob $job, int $delaySeconds = 0): void
    {
        if ($delaySeconds > 0) {
            $this->delayed[] = ['job' => $job, 'availableAt' => $this->now + $delaySeconds];

            return;
        }
        $this->ready[] = $job;
    }

    public function reserve(int $timeoutSeconds): ?QueuedJob
    {
        $this->promote();

        return array_shift($this->ready);
    }

    public function deadLetter(QueuedJob $job, string $error): void
    {
        $this->dead[] = ['job' => $job, 'error' => $error];
    }

    public function retryDeadLetters(): int
    {
        $count = 0;
        foreach ($this->dead as $entry) {
            $this->ready[] = new QueuedJob($entry['job']->jobId, $entry['job']->type, $entry['job']->payload, 0);
            ++$count;
        }
        $this->dead = [];

        return $count;
    }

    /**
     * Advance the virtual clock so delayed retries become due.
     */
    public function advanceSeconds(int $seconds): void
    {
        $this->now += $seconds;
    }

    private function promote(): void
    {
        $stillDelayed = [];
        foreach ($this->delayed as $entry) {
            if ($entry['availableAt'] <= $this->now) {
                $this->ready[] = $entry['job'];
            } else {
                $stillDelayed[] = $entry;
            }
        }
        $this->delayed = $stillDelayed;
    }

    // --- Inspection helpers for tests ---

    /** @return list<QueuedJob> */
    public function readyJobs(): array
    {
        return $this->ready;
    }

    /** @return list<array{job: QueuedJob, availableAt: int}> */
    public function delayedJobs(): array
    {
        return $this->delayed;
    }

    /** @return list<array{job: QueuedJob, error: string}> */
    public function deadLetters(): array
    {
        return $this->dead;
    }
}
