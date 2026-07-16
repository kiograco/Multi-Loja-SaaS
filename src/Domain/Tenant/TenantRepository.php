<?php

declare(strict_types=1);

namespace OrderHub\Domain\Tenant;

interface TenantRepository
{
    public function save(Tenant $tenant): void;

    public function findById(TenantId $id): ?Tenant;

    /**
     * @return list<Tenant>
     */
    public function findByOwner(string $ownerUserId): array;
}
