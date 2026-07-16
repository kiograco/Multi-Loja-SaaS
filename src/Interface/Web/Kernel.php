<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web;

use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Interface\Web\Controller\AuthController;
use OrderHub\Interface\Web\Controller\DashboardController;
use OrderHub\Interface\Web\Controller\OrderController;
use OrderHub\Interface\Web\Controller\ProductController;
use OrderHub\Interface\Web\Http\Router;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use OrderHub\Interface\Web\Middleware\RequireWebAuthMiddleware;
use Throwable;

/**
 * Web application kernel. Mirrors Api\Kernel's shape (route table + a small
 * middleware pipeline) but authenticates via session instead of JWT and
 * renders Twig/HTML instead of JSON. All routes live under /app.
 */
final class Kernel
{
    private const PREFIX = '/app';

    private readonly Router $router;
    private readonly Session $session;
    private readonly RequireWebAuthMiddleware $auth;
    private readonly ErrorHandler $errorHandler;

    public function __construct(private readonly Container $container)
    {
        $this->session = new Session();
        $this->auth = new RequireWebAuthMiddleware($this->session);
        $this->errorHandler = new ErrorHandler($container->twig(), (getenv('APP_ENV') ?: 'dev') !== 'prod');
        $this->router = $this->buildRouter();
    }

    public function handle(WebRequest $request): WebResponse
    {
        $this->session->start();

        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            return $this->errorHandler->toResponse($e);
        }
    }

    private function dispatch(WebRequest $request): WebResponse
    {
        $match = $this->router->match($request);
        if ($match === null) {
            return WebResponse::html($this->container->twig()->render('errors/generic.html.twig', [
                'status' => 404,
                'title' => 'Página não encontrada',
                'message' => 'A página que você procura não existe.',
            ]), 404);
        }

        $attributes = $match['params'];

        if ($match['protected']) {
            $user = $this->auth->authenticate();
            if ($user === null) {
                return WebResponse::redirect(self::PREFIX . '/login');
            }
            $attributes['user_id'] = $user->userId;
            if ($user->hasTenant()) {
                $attributes['tenant_id'] = $user->tenantId();
            }
        }

        return ($match['handler'])($request->withAttributes($attributes));
    }

    private function buildRouter(): Router
    {
        $router = new Router();

        $auth = new AuthController($this->container->loginService(), $this->session, $this->container->twig());
        $dashboard = new DashboardController($this->container->queryBus(), $this->session, $this->container->twig());
        $products = new ProductController($this->container->commandBus(), $this->container->queryBus(), $this->session, $this->container->twig());
        $orders = new OrderController($this->container->commandBus(), $this->container->queryBus(), $this->session, $this->container->twig());

        // Public
        $router->add('GET', self::PREFIX . '/login', $auth->showLogin(...), protected: false);
        $router->add('POST', self::PREFIX . '/login', $auth->login(...), protected: false);
        $router->add('POST', self::PREFIX . '/logout', $auth->logout(...), protected: false);
        $router->add('GET', self::PREFIX . '/ping', fn (WebRequest $r): WebResponse => WebResponse::html($this->container->twig()->render('ping.html.twig')), protected: false);

        // Authenticated
        $router->add('GET', self::PREFIX . '/dashboard', $dashboard->index(...));

        $router->add('GET', self::PREFIX . '/products', $products->list(...));
        $router->add('GET', self::PREFIX . '/products/new', $products->newForm(...));
        $router->add('POST', self::PREFIX . '/products/new', $products->create(...));
        $router->add('GET', self::PREFIX . '/products/{id}/edit', $products->editForm(...));
        $router->add('POST', self::PREFIX . '/products/{id}/edit', $products->update(...));

        $router->add('GET', self::PREFIX . '/orders', $orders->list(...));
        $router->add('GET', self::PREFIX . '/orders/{id}', $orders->detail(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/pay', $orders->pay(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/ship', $orders->ship(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/cancel', $orders->cancel(...));

        return $router;
    }
}
