<?php

declare(strict_types=1);

namespace OrderHub\Application\Queue;

use OrderHub\Application\Support\Logger;
use Throwable;

/**
 * Consumes the job queue with idempotency, exponential-backoff retries and a
 * dead-letter queue.
 *
 * - Before running a job we check the processed_jobs ledger; a job seen before
 *   is acknowledged and skipped (idempotency).
 * - On failure the job is re-enqueued with a growing delay (BACKOFF_SCHEDULE)
 *   until MAX_ATTEMPTS is reached, after which it is dead-lettered.
 * - Success records the job id in the ledger so a duplicate can never re-run it.
 */
final class Worker
{
    /** Attempt N (1-based) waits BACKOFF_SCHEDULE[N-1] seconds before retrying. */
    private const BACKOFF_SCHEDULE = [10, 60, 300];
    private const MAX_ATTEMPTS = 3;

    /** @var array<string, JobHandler> */
    private array $handlers = [];
    private bool $shouldStop = false;

    public function __construct(
        private readonly JobQueue $queue,
        private readonly ProcessedJobLedger $ledger,
        private readonly Logger $logger,
    ) {
    }

    public function registerHandler(JobHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * Run the consume loop. $maxJobs > 0 processes at most that many reservations
     * then returns (useful for tests); 0 means run until stop() is called.
     */
    public function run(int $maxJobs = 0, int $reserveTimeoutSeconds = 5): void
    {
        $processed = 0;
        while (!$this->shouldStop) {
            $job = $this->queue->reserve($reserveTimeoutSeconds);
            if ($job !== null) {
                $this->process($job);
                ++$processed;
            }
            if ($maxJobs > 0 && $processed >= $maxJobs) {
                return;
            }
        }
    }

    public function process(QueuedJob $job): void
    {
        if ($this->ledger->isProcessed($job->jobId)) {
            $this->logger->info('Skipping already-processed job', ['jobId' => $job->jobId]);

            return;
        }

        $handler = $this->handlers[$job->type] ?? null;
        if ($handler === null) {
            $this->queue->deadLetter($job, 'No handler for job type ' . $job->type);

            return;
        }

        try {
            $handler->handle($job);
            $this->ledger->markProcessed($job->jobId, $job->type);
            $this->logger->info('Job processed', ['jobId' => $job->jobId, 'type' => $job->type]);
        } catch (Throwable $e) {
            $this->handleFailure($job, $e);
        }
    }

    private function handleFailure(QueuedJob $job, Throwable $e): void
    {
        $attemptsMade = $job->attempts + 1;
        if ($attemptsMade >= self::MAX_ATTEMPTS) {
            $this->queue->deadLetter($job->nextAttempt(), $e->getMessage());
            $this->logger->error('Job exhausted retries, moved to DLQ', [
                'jobId' => $job->jobId,
                'attempts' => $attemptsMade,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $delay = self::BACKOFF_SCHEDULE[$job->attempts] ?? self::BACKOFF_SCHEDULE[array_key_last(self::BACKOFF_SCHEDULE)];
        $this->queue->enqueue($job->nextAttempt(), $delay);
        $this->logger->error('Job failed, scheduled retry', [
            'jobId' => $job->jobId,
            'attempt' => $attemptsMade,
            'retryInSeconds' => $delay,
            'error' => $e->getMessage(),
        ]);
    }
}
