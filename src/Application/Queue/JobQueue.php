<?php

declare(strict_types=1);

namespace OrderHub\Application\Queue;

/**
 * Port for the job queue. Producers (the side-effect subscriber) call enqueue;
 * the worker reserves jobs, and routes exhausted ones to the dead-letter queue.
 */
interface JobQueue
{
    public function enqueue(QueuedJob $job, int $delaySeconds = 0): void;

    /**
     * Block up to $timeoutSeconds for the next ready job, or return null on timeout.
     */
    public function reserve(int $timeoutSeconds): ?QueuedJob;

    public function deadLetter(QueuedJob $job, string $error): void;

    /**
     * Move every job currently in the dead-letter queue back to the main queue.
     *
     * @return int the number of jobs requeued
     */
    public function retryDeadLetters(): int;
}
