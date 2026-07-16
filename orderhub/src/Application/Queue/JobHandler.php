<?php

declare(strict_types=1);

namespace OrderHub\Application\Queue;

/**
 * Executes one job type. Handlers must be idempotent themselves where possible;
 * the worker additionally guards with the processed_jobs ledger.
 */
interface JobHandler
{
    public function type(): string;

    public function handle(QueuedJob $job): void;
}
