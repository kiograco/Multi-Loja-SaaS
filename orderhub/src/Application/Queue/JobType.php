<?php

declare(strict_types=1);

namespace OrderHub\Application\Queue;

/**
 * Canonical job type names, shared by producers, handlers and the worker's
 * routing table so the strings never drift apart.
 */
final class JobType
{
    public const GENERATE_INVOICE_PDF = 'GenerateInvoicePdf';
    public const SEND_CONFIRMATION_EMAIL = 'SendOrderConfirmationEmail';
    public const DISPATCH_WEBHOOK = 'DispatchWebhook';
    public const DECREMENT_STOCK = 'DecrementStock';
}
