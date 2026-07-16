<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Queue;

use OrderHub\Application\Queue\JobQueue;
use OrderHub\Application\Queue\QueuedJob;
use Redis;

/**
 * Redis-backed job queue.
 *
 * - Ready jobs live in a list consumed with a blocking pop (BRPOP).
 * - Delayed retries live in a sorted set scored by "run-at" epoch; each reserve
 *   first promotes any now-due delayed jobs into the ready list.
 * - Exhausted jobs go to a dead-letter list, annotated with the failure reason.
 */
final class RedisJobQueue implements JobQueue
{
    private const READY = 'orderhub:queue';
    private const DELAYED = 'orderhub:delayed';
    private const DLQ = 'orderhub:dlq';

    public function __construct(private readonly Redis $redis)
    {
    }

    public function enqueue(QueuedJob $job, int $delaySeconds = 0): void
    {
        $encoded = $this->encode($job->toArray());
        if ($delaySeconds > 0) {
            $this->redis->zAdd(self::DELAYED, time() + $delaySeconds, $encoded);

            return;
        }
        $this->redis->lPush(self::READY, $encoded);
    }

    public function reserve(int $timeoutSeconds): ?QueuedJob
    {
        $this->promoteDueDelayedJobs();

        /** @var array{0: string, 1: string}|array{}|false|null $result */
        $result = $this->redis->brPop([self::READY], $timeoutSeconds);
        if (!\is_array($result) || !isset($result[1])) {
            return null;
        }

        return QueuedJob::fromArray($this->decode($result[1]));
    }

    public function deadLetter(QueuedJob $job, string $error): void
    {
        $data = $job->toArray();
        $data['error'] = $error;
        $data['deadLetteredAt'] = date(\DateTimeImmutable::ATOM);
        $this->redis->lPush(self::DLQ, $this->encode($data));
    }

    public function retryDeadLetters(): int
    {
        $count = 0;
        while (true) {
            $raw = $this->redis->rPop(self::DLQ);
            if (!\is_string($raw)) {
                break;
            }
            $data = $this->decode($raw);
            unset($data['error'], $data['deadLetteredAt']);
            // Reset attempts so a manually-retried job gets a fresh retry budget.
            $data['attempts'] = 0;
            $this->redis->lPush(self::READY, $this->encode($data));
            ++$count;
        }

        return $count;
    }

    private function promoteDueDelayedJobs(): void
    {
        $now = time();
        /** @var list<string> $due */
        $due = $this->redis->zRangeByScore(self::DELAYED, '-inf', (string) $now);
        foreach ($due as $member) {
            // zRem guards against another worker promoting the same job.
            if ((int) $this->redis->zRem(self::DELAYED, $member) > 0) {
                $this->redis->lPush(self::READY, $member);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $raw): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
