<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\UpdateTenantSettings\UpdateTenantSettingsCommand;
use OrderHub\Application\Query\GetTenantSettings\GetTenantSettingsQuery;
use OrderHub\Interface\Api\Http\Input;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class TenantController
{
    use CurrentUser;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function create(Request $request): Response
    {
        $user = $this->currentUser($request);
        $input = new Input($request->body());

        $tenantId = $this->commandBus->dispatch(new CreateTenantCommand(
            $user->userId,
            $input->requireString('store_name'),
            $input->optionalString('webhook_url'),
        ));

        return Response::json(['id' => $tenantId], 201);
    }

    public function show(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();

        $settings = $this->queryBus->ask(new GetTenantSettingsQuery($tenantId));

        return Response::json($settings);
    }

    public function update(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $this->commandBus->dispatch(new UpdateTenantSettingsCommand(
            $tenantId,
            $input->requireString('store_name'),
            $input->optionalString('webhook_url'),
        ));

        return Response::json($this->queryBus->ask(new GetTenantSettingsQuery($tenantId)));
    }
}
