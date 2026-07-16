<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Controller;

use OrderHub\Application\Auth\LoginService;
use OrderHub\Interface\Api\Http\Input;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;

final class AuthController
{
    public function __construct(private readonly LoginService $loginService)
    {
    }

    public function login(Request $request): Response
    {
        $input = new Input($request->body());
        $result = $this->loginService->login(
            $input->requireString('email'),
            $input->requireString('password'),
            $input->optionalString('tenant_id'),
        );

        return Response::json($result);
    }
}
