<?php

declare(strict_types=1);

namespace OrderHub\Application\Webhook;

use RuntimeException;

final class WebhookDeliveryException extends RuntimeException
{
    public static function forUrl(string $url, string $reason): self
    {
        return new self(\sprintf('Webhook delivery to %s failed: %s', $url, $reason));
    }
}
