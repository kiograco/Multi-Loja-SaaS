<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\UpdateTenantSettings;

use OrderHub\Application\Bus\Command;

final readonly class UpdateTenantSettingsCommand implements Command
{
    public function __construct(
        public string $tenantId,
        public string $storeName,
        public ?string $webhookUrl = null,
    ) {
    }
}
