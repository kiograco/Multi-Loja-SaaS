<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\CreateTenant;

use OrderHub\Application\Bus\Command;

final readonly class CreateTenantCommand implements Command
{
    public function __construct(
        public string $ownerUserId,
        public string $storeName,
        public ?string $webhookUrl = null,
    ) {
    }
}
