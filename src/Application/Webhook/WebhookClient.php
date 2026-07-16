<?php

declare(strict_types=1);

namespace OrderHub\Application\Webhook;

/**
 * Port for delivering webhooks. A non-2xx response or transport failure must
 * throw, so the worker's retry/DLQ machinery can react.
 */
interface WebhookClient
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return int the HTTP response status code
     *
     * @throws \OrderHub\Application\Webhook\WebhookDeliveryException
     */
    public function post(string $url, array $payload): int;
}
