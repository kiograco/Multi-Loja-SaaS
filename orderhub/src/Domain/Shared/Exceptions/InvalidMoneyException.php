<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared\Exceptions;

final class InvalidMoneyException extends DomainException
{
    public static function negativeAmount(int $cents): self
    {
        return new self(\sprintf('Money amount cannot be negative, got %d cents.', $cents));
    }

    public static function negativeMultiplier(int $factor): self
    {
        return new self(\sprintf('Money multiplier cannot be negative, got %d.', $factor));
    }

    public static function currencyMismatch(string $left, string $right): self
    {
        return new self(\sprintf('Cannot operate on different currencies: %s vs %s.', $left, $right));
    }
}
