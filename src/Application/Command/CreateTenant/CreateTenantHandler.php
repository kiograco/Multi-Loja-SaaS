<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CreateTenant;

use OrderHub\Domain\Shared\Clock;
use OrderHub\Domain\Tenant\Tenant;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;

final class CreateTenantHandler
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateTenantCommand $command): string
    {
        $tenant = new Tenant(
            TenantId::generate(),
            $command->ownerUserId,
            $command->storeName,
            $this->clock->now(),
            $command->webhookUrl,
        );
        $this->tenants->save($tenant);

        return $tenant->id->value;
    }
}
