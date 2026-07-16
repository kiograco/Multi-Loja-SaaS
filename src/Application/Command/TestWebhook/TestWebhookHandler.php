<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\TestWebhook;

use DateTimeImmutable;
use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Application\Webhook\WebhookDeliveryException;
use OrderHub\Application\Webhook\WebhookDeliveryRepository;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;

/**
 * Fires a synchronous test payload at the tenant's configured webhook, on
 * demand — the "Testar webhook agora" button from Seção 18, so the store
 * owner doesn't have to wait for (or fake) a real payment to see whether
 * their endpoint is reachable.
 */
final class TestWebhookHandler
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly WebhookClient $webhookClient,
        private readonly WebhookDeliveryRepository $deliveries,
    ) {
    }

    /**
     * @return array{success: bool, responseCode: ?int, error: ?string}
     */
    public function __invoke(TestWebhookCommand $command): array
    {
        $tenant = $this->tenants->findById(TenantId::fromString($command->tenantId));
        if ($tenant === null) {
            throw AggregateNotFoundException::tenant($command->tenantId);
        }
        if (!$tenant->hasWebhook()) {
            throw ConflictException::because('Nenhum webhook configurado para esta loja.');
        }

        $url = (string) $tenant->webhookUrl();
        $payload = [
            'event' => 'webhook.test',
            'sentAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ];

        try {
            $statusCode = $this->webhookClient->post($url, $payload);
            $this->deliveries->record($tenant->id->value, null, $url, true, $statusCode, null, new DateTimeImmutable());

            return ['success' => true, 'responseCode' => $statusCode, 'error' => null];
        } catch (WebhookDeliveryException $e) {
            $this->deliveries->record($tenant->id->value, null, $url, false, $e->responseCode, $e->getMessage(), new DateTimeImmutable());

            return ['success' => false, 'responseCode' => $e->responseCode, 'error' => $e->getMessage()];
        }
    }
}
