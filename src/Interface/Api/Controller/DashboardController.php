<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class DashboardController
{
    use CurrentUser;

    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function summary(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();

        return Response::json($this->queryBus->ask(new GetDashboardSummaryQuery($tenantId)));
    }
}
