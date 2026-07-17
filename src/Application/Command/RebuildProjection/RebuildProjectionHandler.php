<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\RebuildProjection;

use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Application\Projector\ProjectionRebuilder;

final class RebuildProjectionHandler
{
    public function __construct(private readonly ProjectionRebuilder $rebuilder)
    {
    }

    /**
     * @return list<string> the projection names that were rebuilt
     */
    public function __invoke(RebuildProjectionCommand $command): array
    {
        try {
            return $command->name === 'all' ? $this->rebuilder->rebuildAll() : $this->rebuilder->rebuild($command->name);
        } catch (\InvalidArgumentException $e) {
            // Caller (Web/CLI) is expected to only offer known names; this is a
            // defensive translation into the application's own exception
            // vocabulary rather than letting a low-level exception type leak out.
            throw ConflictException::because($e->getMessage());
        }
    }
}
