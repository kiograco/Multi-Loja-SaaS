<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api;

use OrderHub\Application\Auth\AuthenticatedUser;
use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Interface\Api\Controller\AuthController;
use OrderHub\Interface\Api\Controller\DashboardController;
use OrderHub\Interface\Api\Controller\OrderController;
use OrderHub\Interface\Api\Controller\ProductController;
use OrderHub\Interface\Api\Controller\TenantController;
use OrderHub\Interface\Api\Controller\WebhookController;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;
use OrderHub\Interface\Api\Http\Router;
use OrderHub\Interface\Api\Middleware\Authenticator;
use Throwable;

/**
 * HTTP application kernel. Owns the route table and the middleware pipeline:
 * error handling → routing → authentication → per-tenant rate limiting →
 * controller. All routes live under /api/v1.
 */
final class Kernel
{
    private const PREFIX = '/api/v1';

    private readonly Router $router;
    private readonly Authenticator $authenticator;
    private readonly ErrorHandler $errorHandler;

    public function __construct(private readonly Container $container)
    {
        $this->authenticator = new Authenticator($container->tokenIssuer());
        $this->errorHandler = new ErrorHandler((getenv('APP_ENV') ?: 'dev') !== 'prod');
        $this->router = $this->buildRouter();
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            return $this->errorHandler->toResponse($e);
        }
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request);
        if ($match === null) {
            $status = $this->router->pathExistsForOtherMethod($request) ? 405 : 404;

            return Response::error(
                $status === 405 ? 'METHOD_NOT_ALLOWED' : 'NOT_FOUND',
                $status === 405 ? 'HTTP method not allowed for this route.' : 'Route not found.',
                $status,
            );
        }

        $attributes = $match['params'];

        if ($match['protected']) {
            $user = $this->authenticator->authenticate($request);
            $attributes['user_id'] = $user->userId;
            if ($user->hasTenant()) {
                $attributes['tenant_id'] = $user->tenantId();
                $limited = $this->applyRateLimit($user);
                if ($limited !== null) {
                    return $limited;
                }
            }
        }

        return ($match['handler'])($request->withAttributes($attributes));
    }

    private function applyRateLimit(AuthenticatedUser $user): ?Response
    {
        $result = $this->container->rateLimiter()->hit($user->tenantId());
        if (!$result['allowed']) {
            return Response::error(
                'RATE_LIMIT_EXCEEDED',
                'Too many requests for this tenant. Try again shortly.',
                429,
                [
                    'x-ratelimit-limit' => (string) $result['limit'],
                    'x-ratelimit-remaining' => '0',
                    'retry-after' => '60',
                ],
            );
        }

        return null;
    }

    private function buildRouter(): Router
    {
        $router = new Router();

        $auth = new AuthController($this->container->loginService());
        $tenants = new TenantController($this->container->commandBus(), $this->container->queryBus());
        $products = new ProductController($this->container->commandBus(), $this->container->queryBus());
        $orders = new OrderController($this->container->commandBus(), $this->container->queryBus());
        $dashboard = new DashboardController($this->container->queryBus());
        $webhooks = new WebhookController($this->container->commandBus(), $this->container->queryBus());

        // Public
        $router->add('POST', self::PREFIX . '/auth/login', $auth->login(...), protected: false);

        // Authenticated (tenant may be absent for tenant creation)
        $router->add('POST', self::PREFIX . '/tenants', $tenants->create(...));
        $router->add('GET', self::PREFIX . '/tenants/me', $tenants->show(...));
        $router->add('PATCH', self::PREFIX . '/tenants/me', $tenants->update(...));

        // Tenant-scoped (rate-limited)
        $router->add('GET', self::PREFIX . '/products', $products->list(...));
        $router->add('POST', self::PREFIX . '/products', $products->create(...));
        $router->add('PATCH', self::PREFIX . '/products/{id}', $products->update(...));
        $router->add('DELETE', self::PREFIX . '/products/{id}', $products->delete(...));

        $router->add('POST', self::PREFIX . '/orders', $orders->create(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/pay', $orders->pay(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/ship', $orders->ship(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/deliver', $orders->deliver(...));
        $router->add('POST', self::PREFIX . '/orders/{id}/cancel', $orders->cancel(...));
        $router->add('GET', self::PREFIX . '/orders/{id}', $orders->show(...));
        $router->add('GET', self::PREFIX . '/orders/{id}/invoice', $orders->invoice(...));
        $router->add('GET', self::PREFIX . '/orders/{id}/timeline', $orders->timeline(...));
        $router->add('GET', self::PREFIX . '/orders', $orders->list(...));

        $router->add('GET', self::PREFIX . '/dashboard/summary', $dashboard->summary(...));

        $router->add('GET', self::PREFIX . '/webhooks/deliveries', $webhooks->deliveries(...));
        $router->add('POST', self::PREFIX . '/webhooks/test', $webhooks->test(...));

        return $router;
    }
}
