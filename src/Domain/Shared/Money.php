<?php

declare(strict_types=1);

namespace OrderHub\Domain\Shared;

use OrderHub\Domain\Shared\Exceptions\InvalidMoneyException;

/**
 * Immutable monetary value stored as an integer amount of minor units (cents),
 * avoiding floating-point rounding errors.
 */
final readonly class Money
{
    private function __construct(
        public int $cents,
        public string $currency,
    ) {
    }

    public static function ofCents(int $cents, string $currency = 'BRL'): self
    {
        if ($cents < 0) {
            throw InvalidMoneyException::negativeAmount($cents);
        }

        return new self($cents, $currency);
    }

    public static function zero(string $currency = 'BRL'): self
    {
        return new self(0, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function multipliedBy(int $factor): self
    {
        if ($factor < 0) {
            throw InvalidMoneyException::negativeMultiplier($factor);
        }

        return new self($this->cents * $factor, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function toDecimal(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
    }
}
