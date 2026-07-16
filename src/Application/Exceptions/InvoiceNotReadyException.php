<?php

declare(strict_types=1);

namespace OrderHub\Application\Exceptions;

use RuntimeException;

/**
 * The invoice PDF is generated asynchronously by GenerateInvoicePdfJob after
 * PaymentReceived; this signals the worker hasn't produced it yet (or the
 * order was never paid). Maps to HTTP 404 with a distinct code so clients can
 * tell "not ready" apart from "wrong id".
 */
final class InvoiceNotReadyException extends RuntimeException
{
    public static function forOrder(string $orderId): self
    {
        return new self(\sprintf('Invoice for order %s is not available yet.', $orderId));
    }
}
