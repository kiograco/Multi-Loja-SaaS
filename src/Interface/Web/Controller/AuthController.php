<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Auth\LoginService;
use OrderHub\Application\Exceptions\AuthenticationException;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

final class AuthController
{
    use RendersTemplates;

    public function __construct(
        private readonly LoginService $loginService,
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

        $this->session->set('user_id', $result['userId']);
        $this->session->set('tenant_id', $result['tenantId']);
        $this->session->flash('success', 'Login realizado com sucesso.');

        return WebResponse::redirect('/app/dashboard');
    }

    public function logout(WebRequest $request): WebResponse
    {
        $this->session->destroy();

        return WebResponse::redirect('/app/login');
    }
}
