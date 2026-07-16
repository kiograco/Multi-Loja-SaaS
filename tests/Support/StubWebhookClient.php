<?php

declare(strict_types=1);

namespace OrderHub\Tests\Support;

use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Application\Webhook\WebhookDeliveryException;

/**
 * Test double for the webhook client: records calls and can be told to fail so
 * retry/DLQ behaviour can be exercised without real HTTP.
 */
final class StubWebhookClient implements WebhookClient
{
    /** @var list<array{url: string, payload: array<string, mixed>}> */
    public array $calls = [];
    public bool $shouldFail = false;
    public int $statusCode = 200;

    public function post(string $url, array $payload): int
    {
        $this->calls[] = ['url' => $url, 'payload' => $payload];
        if ($this->shouldFail) {
            throw WebhookDeliveryException::forUrl($url, 'stubbed failure', 500);
        }

        return $this->statusCode;
    }
}
