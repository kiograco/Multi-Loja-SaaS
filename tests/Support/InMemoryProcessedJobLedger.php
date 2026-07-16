<?php

declare(strict_types=1);

namespace OrderHub\Tests\Support;

use OrderHub\Application\Queue\ProcessedJobLedger;

final class InMemoryProcessedJobLedger implements ProcessedJobLedger
{
    /** @var array<string, true> */
    private array $processed = [];

    public function isProcessed(string $jobId): bool
    {
        return isset($this->processed[$jobId]);
    }

    public function markProcessed(string $jobId, string $jobType): bool
    {
        if (isset($this->processed[$jobId])) {
            return false;
        }
        $this->processed[$jobId] = true;

        return true;
    }
}
