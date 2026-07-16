<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListMyTenants;

use OrderHub\Domain\Tenant\Tenant;
use OrderHub\Domain\Tenant\TenantRepository;

/**
 * Backs the Web "trocar de loja" switcher: Tenant::findByOwner() already
 * supported multiple stores per user since Fase 3, but nothing surfaced the
 * list to the session/UI until now.
 */
final class ListMyTenantsHandler
{
    public function __construct(private readonly TenantRepository $tenants)
    {
    }

    /**
     * @return list<array{id: string, storeName: string}>
     */
    public function __invoke(ListMyTenantsQuery $query): array
    {
        return array_map(
            static fn (Tenant $t): array => ['id' => $t->id->value, 'storeName' => $t->storeName()],
            $this->tenants->findByOwner($query->userId),
        );
    }
}
