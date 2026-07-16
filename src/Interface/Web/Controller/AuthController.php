<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Auth\LoginService;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Exceptions\AuthenticationException;
use OrderHub\Application\Query\ListMyTenants\ListMyTenantsQuery;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

final class AuthController
{
    use RendersTemplates;

    public function __construct(
        private readonly LoginService $loginService,
        private readonly QueryBus $queryBus,
        private readonly Session $session,
        private readonly Environment $twig,
    ) {
    }

    public function showLogin(WebRequest $request): WebResponse
    {
        return $this->render($this->twig, $this->session, 'auth/login.html.twig');
    }

    public function login(WebRequest $request): WebResponse
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        try {
            $result = $this->loginService->login($email, $password);
        } catch (AuthenticationException) {
            return $this->render($this->twig, $this->session, 'auth/login.html.twig', [
                'error' => 'E-mail ou senha inválidos.',
            ], 401);
        }

        $stores = $this->queryBus->ask(new ListMyTenantsQuery($result['userId']));

        $this->session->set('user_id', $result['userId']);
        $this->session->set('tenant_id', $result['tenantId']);
        $this->session->set('stores', $stores);
        $this->session->flash('success', 'Login realizado com sucesso.');

        return WebResponse::redirect('/app/dashboard');
    }

    public function logout(WebRequest $request): WebResponse
    {
        $this->session->destroy();

        return WebResponse::redirect('/app/login');
    }

    /**
     * Switches the active store in the session without requiring a fresh
     * login — Tenant::findByOwner() already supported owners with several
     * stores, this is the piece that lets the Web session act on it.
     */
    public function switchTenant(WebRequest $request): WebResponse
    {
        $requestedTenantId = (string) $request->attribute('tenantId');

        /** @var list<array{id: string, storeName: string}> $stores */
        $stores = $this->session->get('stores', []);
        $owned = array_column($stores, 'id');

        if (!\in_array($requestedTenantId, $owned, true)) {
            return $this->render($this->twig, $this->session, 'errors/generic.html.twig', [
                'status' => 403,
                'title' => 'Acesso negado',
                'message' => 'Esta loja não pertence à sua conta.',
            ], 403);
        }

        $this->session->set('tenant_id', $requestedTenantId);
        $this->session->flash('success', 'Loja ativa alterada com sucesso.');

        return WebResponse::redirect('/app/dashboard');
    }
}
