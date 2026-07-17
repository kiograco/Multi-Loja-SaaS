<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\RetryDeadLetterQueue;

use OrderHub\Application\Queue\JobQueue;

final class RetryDeadLetterQueueHandler
{
    public function __construct(private readonly JobQueue $queue)
    {
    }

    public function __invoke(RetryDeadLetterQueueCommand $command): int
    {
        return $this->queue->retryDeadLetters();
    }
}
