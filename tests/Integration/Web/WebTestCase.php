<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Web;

use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Infrastructure\Config\Env;
use OrderHub\Infrastructure\Persistence\Database;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use OrderHub\Interface\Web\Kernel;
use OrderHub\Tests\Integration\IntegrationTestCase;
use OrderHub\Tests\Support\StubWebhookClient;
use Redis;

/**
 * Boots the real Web kernel against the test Postgres/Redis and drives it with
 * synthetic requests — the Web-channel counterpart of Api\ApiTestCase. Identity
 * is carried by the PHP session ($_SESSION) instead of a bearer token, so each
 * test starts from a clean session the same way ApiTestCase starts from a
 * clean database.
 */
abstract class WebTestCase extends IntegrationTestCase
{
    protected Container $container;
    protected Kernel $kernel;
    protected StubWebhookClient $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $redis = new Redis();
        $redis->connect(Env::get('REDIS_HOST', '127.0.0.1'), Env::int('REDIS_PORT', 6379));
        $redis->flushDB();

        $this->container = new Container();
        $this->container->set(Database::class, $this->database);
        $this->container->set(Redis::class, $redis);
        $this->webhook = new StubWebhookClient();
        $this->container->set(WebhookClient::class, $this->webhook);

        $this->resetSession();

        $this->kernel = new Kernel($this->container);
    }

    protected function tearDown(): void
    {
        $this->resetSession();
        parent::tearDown();
    }

    /**
     * @param array<string, string> $formData
     * @param array<string, string> $query
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $path, array $formData = [], array $query = [], array $headers = []): WebResponse
    {
        return $this->kernel->handle(new WebRequest($method, $path, $headers, $query, $formData));
    }

    /**
     * Logs a fresh owner+store in through the real login flow, so tests get a
     * session (user_id + tenant_id) exactly the way a browser would.
     */
    protected function loginAsNewOwner(string $email = 'owner@shop.test', string $password = 'secret123', string $storeName = 'My Shop'): string
    {
        $userId = $this->container->commandBus()->dispatch(new \OrderHub\Application\Command\RegisterUser\RegisterUserCommand($email, $password));
        $this->container->commandBus()->dispatch(new \OrderHub\Application\Command\CreateTenant\CreateTenantCommand((string) $userId, $storeName));

        $login = $this->request('POST', '/app/login', ['email' => $email, 'password' => $password]);
        self::assertSame(302, $login->status);
        self::assertSame('/app/dashboard', $login->headers['location'] ?? null);

        return (string) $this->container->loginService()->login($email, $password)['tenantId'];
    }

    private function resetSession(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
    }
}
