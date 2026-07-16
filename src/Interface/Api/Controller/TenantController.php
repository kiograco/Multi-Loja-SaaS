<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Interface\Api\Http\Input;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class TenantController
{
    use CurrentUser;

    public function __construct(private readonly CommandBus $commandBus)
    {
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
}
