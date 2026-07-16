<?php

declare(strict_types=1);

namespace OrderHub\Domain\Tenant\Exceptions;

use OrderHub\Domain\Shared\Exceptions\DomainException;

final class InvalidTenantException extends DomainException
{
    public static function blankStoreName(): self
    {
        return new self('Store name cannot be blank.');
    }

    public static function invalidWebhookUrl(string $url): self
    {
        return new self(\sprintf('"%s" is not a valid webhook URL.', $url));
    }
}
