<?php

declare(strict_types=1);

namespace OrderHub\Application\Queue;

/**
 * Idempotency ledger. `markProcessed` returns false if the job id was already
 * recorded, letting the worker skip duplicate side effects atomically.
 */
interface ProcessedJobLedger
{
    public function isProcessed(string $jobId): bool;

    /**
     * @return bool true if this call recorded the job, false if it was already present
     */
    public function markProcessed(string $jobId, string $jobType): bool;
}
