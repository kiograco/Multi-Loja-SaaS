<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\TestWebhook;

use OrderHub\Application\Bus\Command;

final readonly class TestWebhookCommand implements Command
{
    public function __construct(public string $tenantId)
    {
    }
}
