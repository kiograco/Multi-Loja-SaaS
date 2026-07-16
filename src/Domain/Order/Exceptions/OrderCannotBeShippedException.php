<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Exceptions;

use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Shared\Exceptions\DomainException;

final class OrderCannotBeShippedException extends DomainException
{
    public static function inStatus(OrderStatus $status): self
    {
        return new self(\sprintf(
            'An order can only be shipped while "%s"; current status is "%s".',
            OrderStatus::Paid->value,
            $status->value,
        ));
    }
}
