<?php

declare(strict_types=1);

namespace OrderHub\Tests\Unit\Domain\Shared;

use OrderHub\Domain\Shared\Exceptions\InvalidMoneyException;
use OrderHub\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAddsSameCurrency(): void
    {
        $sum = Money::ofCents(1000)->add(Money::ofCents(250));
        self::assertSame(1250, $sum->cents);
    }

    public function testMultiplies(): void
    {
        self::assertSame(750, Money::ofCents(250)->multipliedBy(3)->cents);
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(InvalidMoneyException::class);
        Money::ofCents(-1);
    }

    public function testRejectsCurrencyMismatch(): void
    {
        $this->expectException(InvalidMoneyException::class);
        Money::ofCents(100, 'BRL')->add(Money::ofCents(100, 'USD'));
    }

    public function testDecimalFormatting(): void
    {
        self::assertSame('16.05', Money::ofCents(1605)->toDecimal());
    }
}
