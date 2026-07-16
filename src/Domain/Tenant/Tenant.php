<?php

declare(strict_types=1);

namespace OrderHub\Domain\Tenant;

use DateTimeImmutable;
use OrderHub\Domain\Tenant\Exceptions\InvalidTenantException;

/**
 * A store (tenant). Owned by a single user; carries the discriminator that
 * scopes every product, order and projection in the system.
 */
final class Tenant
{
    private string $storeName;
    private ?string $webhookUrl;

    public function __construct(
        public readonly TenantId $id,
        public readonly string $ownerUserId,
        string $storeName,
        public readonly DateTimeImmutable $createdAt,
        ?string $webhookUrl = null,
    ) {
        $this->rename($storeName);
        $this->configureWebhook($webhookUrl);
    }

    public function rename(string $storeName): void
    {
        $storeName = trim($storeName);
        if ($storeName === '') {
            throw InvalidTenantException::blankStoreName();
        }
        $this->storeName = $storeName;
    }

    public function configureWebhook(?string $webhookUrl): void
    {
        if ($webhookUrl !== null && $webhookUrl !== '') {
            if (!filter_var($webhookUrl, \FILTER_VALIDATE_URL)) {
                throw InvalidTenantException::invalidWebhookUrl($webhookUrl);
            }
            $this->webhookUrl = $webhookUrl;

            return;
        }

        $this->webhookUrl = null;
    }

    public function storeName(): string
    {
        return $this->storeName;
    }

    public function webhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function hasWebhook(): bool
    {
        return $this->webhookUrl !== null;
    }
}
