<?php

declare(strict_types=1);

namespace OrderHub\Tests\Unit\Application\Queue;

use OrderHub\Application\Queue\JobHandler;
use OrderHub\Application\Queue\QueuedJob;
use OrderHub\Application\Queue\Worker;
use OrderHub\Infrastructure\Queue\InMemoryJobQueue;
use OrderHub\Tests\Support\InMemoryProcessedJobLedger;
use OrderHub\Tests\Support\NullLogger;
use PHPUnit\Framework\TestCase;

final class WorkerTest extends TestCase
{
    public function testFailingJobRetriesWithBackoffThenGoesToDlq(): void
    {
        $queue = new InMemoryJobQueue();
        $worker = new Worker($queue, new InMemoryProcessedJobLedger(), new NullLogger());

        $handler = new class () implements JobHandler {
            public int $calls = 0;

            public function type(): string
            {
                return 'AlwaysFails';
            }

            public function handle(QueuedJob $job): void
            {
                ++$this->calls;
                throw new \RuntimeException('boom');
            }
        };
        $worker->registerHandler($handler);

        $job = new QueuedJob('AlwaysFails:1', 'AlwaysFails', ['x' => 1]);

        // Attempt 1 -> schedule retry (delay 10s)
        $worker->process($job);
        self::assertCount(0, $queue->deadLetters());
        self::assertCount(1, $queue->delayedJobs());

        // Move time forward, reserve the retry, attempt 2 -> schedule retry (delay 60s)
        $queue->advanceSeconds(10);
        $retry1 = $queue->reserve(0);
        self::assertNotNull($retry1);
        self::assertSame(1, $retry1->attempts);
        $worker->process($retry1);
        self::assertCount(0, $queue->deadLetters());

        // Attempt 3 -> exhausted -> DLQ
        $queue->advanceSeconds(60);
        $retry2 = $queue->reserve(0);
        self::assertNotNull($retry2);
        self::assertSame(2, $retry2->attempts);
        $worker->process($retry2);

        self::assertCount(1, $queue->deadLetters());
        self::assertSame(3, $handler->calls);
        self::assertSame('boom', $queue->deadLetters()[0]['error']);
    }

    public function testAlreadyProcessedJobIsSkipped(): void
    {
        $queue = new InMemoryJobQueue();
        $ledger = new InMemoryProcessedJobLedger();
        $ledger->markProcessed('once:1', 'Once');
        $worker = new Worker($queue, $ledger, new NullLogger());

        $handler = new class () implements JobHandler {
            public int $calls = 0;

            public function type(): string
            {
                return 'Once';
            }

            public function handle(QueuedJob $job): void
            {
                ++$this->calls;
            }
        };
        $worker->registerHandler($handler);

        $worker->process(new QueuedJob('once:1', 'Once', []));

        self::assertSame(0, $handler->calls, 'Idempotency: a processed job must not run again.');
    }

    public function testRetryDlqRequeuesJobs(): void
    {
        $queue = new InMemoryJobQueue();
        $queue->deadLetter(new QueuedJob('j:1', 'T', []), 'err');
        $queue->deadLetter(new QueuedJob('j:2', 'T', []), 'err');

        self::assertSame(2, $queue->retryDeadLetters());
        self::assertCount(0, $queue->deadLetters());
        self::assertCount(2, $queue->readyJobs());
    }
}
