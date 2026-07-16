<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetTenantSettings;

use OrderHub\Application\Bus\Query;

final readonly class GetTenantSettingsQuery implements Query
{
    public function __construct(public string $tenantId)
    {
    }
}
