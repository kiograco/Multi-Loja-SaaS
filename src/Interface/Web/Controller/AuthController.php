<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Auth\LoginService;
use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;
use OrderHub\Application\Exceptions\AuthenticationException;
use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Application\Query\ListMyTenants\ListMyTenantsQuery;
use OrderHub\Domain\Shared\Exceptions\DomainException;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

final class AuthController
{
    use RendersTemplates;

    public function __construct(
        private readonly LoginService $loginService,
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly Session $session,
        private readonly Environment $twig,
    ) {
    }

    public function showLogin(WebRequest $request): WebResponse
    {
        return $this->render($this->twig, $this->session, 'auth/login.html.twig');
    }

    public function showSignup(WebRequest $request): WebResponse
    {
        return $this->render($this->twig, $this->session, 'auth/signup.html.twig', ['errors' => []]);
    }

    /**
     * Self-service signup: registers the owner account and their first store
     * in one step, then signs them in — same RegisterUserCommand/CreateTenantCommand
     * the CLI's tenant:create already uses. No e-mail verification or billing
     * (Seção 19): a portfolio-scope signup, not a production onboarding flow.
     */
    public function signup(WebRequest $request): WebResponse
    {
        $storeName = trim((string) $request->input('storeName', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $errors = [];
        if ($storeName === '') {
            $errors[] = 'Nome da loja é obrigatório.';
        }
        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }
        if (\strlen($password) < 8) {
            $errors[] = 'A senha precisa ter ao menos 8 caracteres.';
        }

        // Store name is validated upfront (not left to CreateTenantCommand)
        // because RegisterUserCommand isn't transactional with it: a domain
        // failure on the second step would otherwise leave an orphaned user
        // whose e-mail is now permanently "taken" for a retry.
        if ($errors !== []) {
            return $this->render($this->twig, $this->session, 'auth/signup.html.twig', [
                'errors' => $errors,
                'storeName' => $storeName,
                'email' => $email,
            ], 422);
        }

        try {
            $userId = (string) $this->commandBus->dispatch(new RegisterUserCommand($email, $password));
            $tenantId = (string) $this->commandBus->dispatch(new CreateTenantCommand($userId, $storeName));
        } catch (ConflictException|DomainException $e) {
            return $this->render($this->twig, $this->session, 'auth/signup.html.twig', [
                'errors' => [$e->getMessage()],
                'storeName' => $storeName,
                'email' => $email,
            ], 422);
        }

        $stores = $this->queryBus->ask(new ListMyTenantsQuery($userId));

        $this->session->set('user_id', $userId);
        $this->session->set('tenant_id', $tenantId);
        $this->session->set('stores', $stores);
        $this->session->flash('success', 'Conta criada com sucesso! Bem-vindo ao OrderHub.');

        return WebResponse::redirect('/app/dashboard');
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
