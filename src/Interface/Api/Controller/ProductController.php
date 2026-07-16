<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\UpdateProduct\UpdateProductCommand;
use OrderHub\Application\Query\ListProducts\ListProductsQuery;
use OrderHub\Interface\Api\Http\Input;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class ProductController
{
    use CurrentUser;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function list(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();

        return Response::json(['data' => $this->queryBus->ask(new ListProductsQuery($tenantId))]);
    }

    public function create(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $id = $this->commandBus->dispatch(new CreateProductCommand(
            $tenantId,
            $input->requireString('name'),
            $input->requireInt('priceCents'),
            $input->requireInt('stockQuantity'),
            $input->optionalString('currency') ?? 'BRL',
        ));

        return Response::json(['id' => $id], 201);
    }

    public function update(Request $request): Response
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $input = new Input($request->body());

        $this->commandBus->dispatch(new UpdateProductCommand(
            $tenantId,
            (string) $request->attribute('id'),
            $input->optionalString('name'),
            $input->optionalInt('priceCents'),
            $input->optionalInt('stockQuantity'),
            $input->optionalString('currency') ?? 'BRL',
        ));

        return Response::noContent();
    }
}
