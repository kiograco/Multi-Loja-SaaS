<?php

declare(strict_types=1);

namespace OrderHub\Application\Webhook;

use RuntimeException;

final class WebhookDeliveryException extends RuntimeException
{
    private function __construct(string $message, public readonly ?int $responseCode = null)
    {
        parent::__construct($message);
    }

    public static function forUrl(string $url, string $reason, ?int $responseCode = null): self
    {
        return new self(\sprintf('Webhook delivery to %s failed: %s', $url, $reason), $responseCode);
    }
}
