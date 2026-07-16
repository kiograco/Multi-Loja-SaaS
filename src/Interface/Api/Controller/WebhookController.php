<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\TestWebhook\TestWebhookCommand;
use OrderHub\Application\Query\ListWebhookDeliveries\ListWebhookDeliveriesQuery;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class WebhookController
{
    use CurrentUser;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function deliveries(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $limit = (int) ($request->query('limit') ?? '20');

        return Response::json(['data' => $this->queryBus->ask(new ListWebhookDeliveriesQuery($tenantId, $limit))]);
    }

    public function test(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();

        return Response::json($this->commandBus->dispatch(new TestWebhookCommand($tenantId)));
    }
}
