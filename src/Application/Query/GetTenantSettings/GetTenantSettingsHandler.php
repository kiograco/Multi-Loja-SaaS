<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetTenantSettings;

use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;

final class GetTenantSettingsHandler
{
    public function __construct(private readonly TenantRepository $tenants)
    {
    }

    /**
     * @return array{id: string, storeName: string, webhookUrl: ?string}
     */
    public function __invoke(GetTenantSettingsQuery $query): array
    {
        $tenant = $this->tenants->findById(TenantId::fromString($query->tenantId));
        if ($tenant === null) {
            throw AggregateNotFoundException::tenant($query->tenantId);
        }

        return [
            'id' => $tenant->id->value,
            'storeName' => $tenant->storeName(),
            'webhookUrl' => $tenant->webhookUrl(),
        ];
    }
}
