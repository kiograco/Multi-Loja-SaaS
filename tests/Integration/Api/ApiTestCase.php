<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Infrastructure\Config\Env;
use OrderHub\Infrastructure\Persistence\Database;
use OrderHub\Interface\Api\Http\Request;
use OrderHub\Interface\Api\Http\Response;
use OrderHub\Interface\Api\Kernel;
use OrderHub\Tests\Integration\IntegrationTestCase;
use OrderHub\Tests\Support\StubWebhookClient;
use Redis;

/**
 * Boots the real HTTP kernel against the test Postgres/Redis and drives it with
 * synthetic requests, so the full API stack (routing, auth, rate limit, buses,
 * projectors) is exercised end to end.
 */
abstract class ApiTestCase extends IntegrationTestCase
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

        $this->kernel = new Kernel($this->container);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $query additional query params (merged with $status when both are given)
     */
    protected function request(string $method, string $path, array $body = [], ?string $token = null, ?string $status = null, array $query = []): Response
    {
        $headers = ['content-type' => 'application/json'];
        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }
        if ($status !== null) {
            $query['status'] = $status;
        }

        return $this->kernel->handle(new Request($method, $path, $headers, $query, $body));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        if ($response->body === '') {
            return [];
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($response->body, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
