<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Exceptions;

use RuntimeException;

/**
 * Malformed or missing request input. Maps to HTTP 422.
 */
final class ValidationException extends RuntimeException
{
    public static function missing(string $field): self
    {
        return new self(\sprintf('Field "%s" is required.', $field));
    }

    public static function invalid(string $field, string $detail): self
    {
        return new self(\sprintf('Field "%s" is invalid: %s', $field, $detail));
    }
}
