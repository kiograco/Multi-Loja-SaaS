<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Queue\Jobs;

use OrderHub\Application\Queue\JobHandler;
use OrderHub\Application\Queue\JobType;
use OrderHub\Application\Queue\QueuedJob;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Application\Support\Logger;

/**
 * Simulates sending the order confirmation e-mail by logging it (per spec, no
 * real SMTP integration is required).
 */
final class SendOrderConfirmationEmailJob implements JobHandler
{
    public function __construct(
        private readonly OrderSummaryReadStore $orders,
        private readonly Logger $logger,
    ) {
    }

    public function type(): string
    {
        return JobType::SEND_CONFIRMATION_EMAIL;
    }

    public function handle(QueuedJob $job): void
    {
        $orderId = (string) $job->payload['orderId'];
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return;
        }

        $this->logger->info('[EMAIL] Order confirmation sent', [
            'to' => $order->customerEmail,
            'orderId' => $order->orderId,
            'total' => number_format($order->totalCents / 100, 2, '.', ''),
            'currency' => $order->currency,
        ]);
    }
}
