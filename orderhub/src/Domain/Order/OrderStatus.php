<?php

declare(strict_types=1);

namespace OrderHub\Domain\Order;

/**
 * Order lifecycle. The spec's "pagamento pendente" is not a distinct persisted
 * state: an order sits in CREATED awaiting payment, so it collapses into
 * CREATED here. Values are the Portuguese business terms used across the API.
 */
enum OrderStatus: string
{
    case Created = 'criado';
    case Paid = 'pago';
    case Shipped = 'enviado';
    case Delivered = 'entregue';
    case Cancelled = 'cancelado';
}
