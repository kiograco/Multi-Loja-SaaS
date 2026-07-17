<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\RebuildProjection;

use OrderHub\Application\Bus\Command;

final readonly class RebuildProjectionCommand implements Command
{
    /**
     * @param string $name a known projection name, or "all"
     */
    public function __construct(public string $name)
    {
    }
}
