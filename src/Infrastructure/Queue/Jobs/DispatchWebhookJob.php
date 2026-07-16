<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Queue\Jobs;

use DateTimeImmutable;
use OrderHub\Application\Queue\JobHandler;
use OrderHub\Application\Queue\JobType;
use OrderHub\Application\Queue\QueuedJob;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Application\Webhook\WebhookDeliveryException;
use OrderHub\Application\Webhook\WebhookDeliveryRepository;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;

/**
 * If the order's tenant has a webhook configured, POSTs the order summary to it.
 * Delivery failures propagate so the worker retries and eventually dead-letters.
 * Every attempt (success or failure) is recorded so the store owner has
 * visibility beyond the server log (Seção 6/18).
 */
final class DispatchWebhookJob implements JobHandler
{
    public function __construct(
        private readonly OrderSummaryReadStore $orders,
        private readonly TenantRepository $tenants,
        private readonly WebhookClient $webhookClient,
        private readonly WebhookDeliveryRepository $deliveries,
    ) {
    }

    public function type(): string
    {
        return JobType::DISPATCH_WEBHOOK;
    }

    public function handle(QueuedJob $job): void
    {
        $orderId = (string) $job->payload['orderId'];
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return;
        }

        $tenant = $this->tenants->findById(TenantId::fromString($order->tenantId));
        if ($tenant === null || !$tenant->hasWebhook()) {
            return;
        }

        $url = (string) $tenant->webhookUrl();

        try {
            $statusCode = $this->webhookClient->post($url, [
                'event' => 'order.paid',
                'order' => $order->toArray(),
            ]);
            $this->deliveries->record($tenant->id->value, $orderId, $url, true, $statusCode, null, new DateTimeImmutable());
        } catch (WebhookDeliveryException $e) {
            $this->deliveries->record($tenant->id->value, $orderId, $url, false, $e->responseCode, $e->getMessage(), new DateTimeImmutable());

            throw $e;
        }
    }
}
