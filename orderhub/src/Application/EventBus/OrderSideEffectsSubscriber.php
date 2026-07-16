<?php

declare(strict_types=1);

namespace OrderHub\Application\EventBus;

use OrderHub\Application\Queue\JobQueue;
use OrderHub\Application\Queue\JobType;
use OrderHub\Application\Queue\QueuedJob;
use OrderHub\Domain\Order\Events\PaymentReceived;
use OrderHub\Domain\Shared\DomainEvent;

/**
 * On payment, fans out the four external side effects onto the queue. Only
 * enqueuing happens synchronously (fast); the actual work runs in the worker.
 *
 * Each job id is deterministic ("<type>:<orderId>") so a replayed PaymentReceived
 * enqueues jobs the worker will recognise as already processed — idempotency end
 * to end.
 */
final class OrderSideEffectsSubscriber implements EventSubscriber
{
    public function __construct(private readonly JobQueue $queue)
    {
    }

    public function on(DomainEvent $event): void
    {
        if (!$event instanceof PaymentReceived) {
            return;
        }

        $payload = ['orderId' => $event->orderId];
        foreach ([
            JobType::DECREMENT_STOCK,
            JobType::GENERATE_INVOICE_PDF,
            JobType::SEND_CONFIRMATION_EMAIL,
            JobType::DISPATCH_WEBHOOK,
        ] as $type) {
            $this->queue->enqueue(new QueuedJob(
                jobId: $type . ':' . $event->orderId,
                type: $type,
                payload: $payload,
            ));
        }
    }
}
