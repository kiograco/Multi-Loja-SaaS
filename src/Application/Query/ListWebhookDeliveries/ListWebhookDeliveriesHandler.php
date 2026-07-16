<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListWebhookDeliveries;

use OrderHub\Application\Webhook\WebhookDeliveryRepository;

final class ListWebhookDeliveriesHandler
{
    public function __construct(private readonly WebhookDeliveryRepository $deliveries)
    {
    }

    /**
     * @return list<array{orderId: ?string, url: string, success: bool, responseCode: ?int, error: ?string, attemptedAt: string}>
     */
    public function __invoke(ListWebhookDeliveriesQuery $query): array
    {
        return $this->deliveries->listForTenant($query->tenantId, $query->limit);
    }
}
