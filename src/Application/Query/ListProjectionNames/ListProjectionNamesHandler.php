<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\ListProjectionNames;

use OrderHub\Application\Projector\ProjectionRebuilder;

final class ListProjectionNamesHandler
{
    public function __construct(private readonly ProjectionRebuilder $rebuilder)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListProjectionNamesQuery $query): array
    {
        return $this->rebuilder->names();
    }
}
