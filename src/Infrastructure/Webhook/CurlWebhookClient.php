<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Webhook;

use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Application\Webhook\WebhookDeliveryException;

/**
 * Delivers webhooks over HTTP with cURL. Any transport error or non-2xx status
 * throws, which the worker turns into a retry and eventually a dead-letter.
 */
final class CurlWebhookClient implements WebhookClient
{
    public function __construct(private readonly int $timeoutSeconds = 5)
    {
    }

    public function post(string $url, array $payload): void
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR);
        $handle = curl_init($url);
        if ($handle === false) {
            throw WebhookDeliveryException::forUrl($url, 'could not initialise cURL');
        }

        curl_setopt_array($handle, [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $body,
            \CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: OrderHub-Webhook/1'],
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $error !== '') {
            throw WebhookDeliveryException::forUrl($url, $error !== '' ? $error : 'transport error');
        }
        if ($status < 200 || $status >= 300) {
            throw WebhookDeliveryException::forUrl($url, 'unexpected HTTP status ' . $status);
        }
    }
}
