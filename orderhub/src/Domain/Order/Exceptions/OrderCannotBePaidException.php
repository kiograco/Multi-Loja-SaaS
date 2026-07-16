<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order\Exceptions;

use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Shared\Exceptions\DomainException;

final class OrderCannotBePaidException extends DomainException
{
    public static function inStatus(OrderStatus $status): self
    {
        return new self(\sprintf(
            'Payment can only be received while the order is "%s"; current status is "%s".',
            OrderStatus::Created->value,
            $status->value,
        ));
    }
}
