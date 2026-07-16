<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

final class DashboardController
{
    use CurrentUser;
    use RendersTemplates;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Session $session,
        private readonly Environment $twig,
    ) {
    }

    public function index(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $topProductsLimit = (int) ($request->query('topProductsLimit') ?? '5');

        $summary = $this->queryBus->ask(new GetDashboardSummaryQuery($tenantId, $topProductsLimit));

        return $this->render($this->twig, $this->session, 'dashboard/index.html.twig', [
            'summary' => $summary,
            'topProductsLimit' => $topProductsLimit,
        ]);
    }
}
