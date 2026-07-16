<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Exceptions;

use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Shared\Exceptions\DomainException;

final class OrderCannotBeCancelledException extends DomainException
{
    public static function inStatus(OrderStatus $status): self
    {
        return new self(\sprintf(
            'An order can no longer be cancelled once "%s"; current status is "%s".',
            OrderStatus::Shipped->value,
            $status->value,
        ));
    }
}
