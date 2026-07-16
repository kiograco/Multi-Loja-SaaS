<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\UpdateTenantSettings;

use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;

/**
 * Exercises Tenant::rename() and Tenant::configureWebhook() — both already
 * existed on the aggregate but had no command wired up to reach them, so the
 * store name and webhook URL were only ever settable at tenant creation.
 */
final class UpdateTenantSettingsHandler
{
    public function __construct(private readonly TenantRepository $tenants)
    {
    }

    public function __invoke(UpdateTenantSettingsCommand $command): void
    {
        $tenant = $this->tenants->findById(TenantId::fromString($command->tenantId));
        if ($tenant === null) {
            throw AggregateNotFoundException::tenant($command->tenantId);
        }

        $tenant->rename($command->storeName);
        $tenant->configureWebhook($command->webhookUrl);
        $this->tenants->save($tenant);
    }
}
