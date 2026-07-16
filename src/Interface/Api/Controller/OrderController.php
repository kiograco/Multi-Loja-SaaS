<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CancelOrder\CancelOrderCommand;
use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Command\ShipOrder\ShipOrderCommand;
use OrderHub\Application\Query\GetOrderSummary\GetOrderSummaryQuery;
use OrderHub\Application\Query\ListOrders\ListOrdersQuery;
use OrderHub\Application\ReadModel\OrderSummary;
use OrderHub\Interface\Api\Http\Input;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class OrderController
{
    use CurrentUser;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $id = $this->commandBus->dispatch(new CreateOrderCommand(
            $tenantId,
            $input->requireString('customerName'),
            $input->requireString('customerEmail'),
            $input->requireOrderItems('items'),
        ));

        return Response::json(['id' => $id], 201);
    }

    public function pay(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $this->commandBus->dispatch(new PayOrderCommand(
            $tenantId,
            (string) $request->attribute('id'),
            $input->optionalString('paymentMethod') ?? 'unspecified',
        ));

        return Response::json(['status' => 'pago']);
    }

    public function ship(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $this->commandBus->dispatch(new ShipOrderCommand(
            $tenantId,
            (string) $request->attribute('id'),
            $input->requireString('trackingCode'),
        ));

        return Response::json(['status' => 'enviado']);
    }

    public function cancel(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $this->commandBus->dispatch(new CancelOrderCommand(
            $tenantId,
            (string) $request->attribute('id'),
            $input->optionalString('reason') ?? 'unspecified',
        ));

        return Response::json(['status' => 'cancelado']);
    }

    public function show(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();

        /** @var OrderSummary $summary */
        $summary = $this->queryBus->ask(new GetOrderSummaryQuery($tenantId, (string) $request->attribute('id')));

        return Response::json($summary->toArray());
    }

    public function list(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();

        $result = $this->queryBus->ask(new ListOrdersQuery(
            $tenantId,
            $request->query('status'),
            (int) ($request->query('page') ?? '1'),
            (int) ($request->query('perPage') ?? '20'),
        ));

        return Response::json($result);
    }
}
