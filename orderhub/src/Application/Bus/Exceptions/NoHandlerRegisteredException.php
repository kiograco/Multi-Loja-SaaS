<?php

declare(strict_types=1);

namespace OrderHub\Application\Bus\Exceptions;

use RuntimeException;

final class NoHandlerRegisteredException extends RuntimeException
{
    public static function forMessage(string $messageClass): self
    {
        return new self(\sprintf('No handler registered for message "%s".', $messageClass));
    }
}
