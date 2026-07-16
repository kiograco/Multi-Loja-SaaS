<?php

declare(strict_types=1);

namespace OrderHub\Tests\Support;

use OrderHub\Application\Support\Logger;

final class NullLogger implements Logger
{
    public function info(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
