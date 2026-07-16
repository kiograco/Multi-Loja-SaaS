<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use DateTimeImmutable;
use OrderHub\Domain\Tenant\Tenant;
use OrderHub\Domain\Tenant\TenantId;
use OrderHub\Domain\Tenant\TenantRepository;

final class PostgresTenantRepository implements TenantRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function save(Tenant $tenant): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO tenants (id, owner_user_id, store_name, webhook_url, created_at)
             VALUES (:id, :owner, :name, :webhook, :created_at)
             ON CONFLICT (id) DO UPDATE SET
                store_name = EXCLUDED.store_name,
                webhook_url = EXCLUDED.webhook_url'
        );
        $stmt->execute([
            'id' => $tenant->id->value,
            'owner' => $tenant->ownerUserId,
            'name' => $tenant->storeName(),
            'webhook' => $tenant->webhookUrl(),
            'created_at' => $tenant->createdAt->format(DateTimeImmutable::ATOM),
        ]);
    }

    public function findById(TenantId $id): ?Tenant
    {
        $stmt = $this->database->pdo()->prepare('SELECT * FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByOwner(string $ownerUserId): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT * FROM tenants WHERE owner_user_id = :owner ORDER BY created_at ASC'
        );
        $stmt->execute(['owner' => $ownerUserId]);

        $tenants = [];
        /** @var array{id: string, owner_user_id: string, store_name: string, webhook_url: ?string, created_at: string} $row */
        foreach ($stmt as $row) {
            $tenants[] = $this->hydrate($row);
        }

        return $tenants;
    }

    /**
     * @param array{id: string, owner_user_id: string, store_name: string, webhook_url: ?string, created_at: string} $row
     */
    private function hydrate(array $row): Tenant
    {
        return new Tenant(
            TenantId::fromString($row['id']),
            $row['owner_user_id'],
            $row['store_name'],
            new DateTimeImmutable($row['created_at']),
            $row['webhook_url'],
        );
    }
}
