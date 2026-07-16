<?php

declare(strict_types=1);

namespace OrderHub\Application\Query\GetOrderInvoice;

use OrderHub\Application\Exceptions\InvoiceNotReadyException;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Reads the invoice PDF that GenerateInvoicePdfJob writes to disk once
 * PaymentReceived is processed by the worker. The file may legitimately not
 * exist yet (async job still queued) or never (order was never paid) — both
 * surface as InvoiceNotReadyException rather than a hard 500.
 */
final class GetOrderInvoiceHandler
{
    public function __construct(
        private readonly OrderSummaryReadStore $orders,
        private readonly string $invoiceDirectory,
    ) {
    }

    public function __invoke(GetOrderInvoiceQuery $query): string
    {
        // Tenant-scoped read: an order of another tenant is simply "not found".
        if ($this->orders->findForTenant($query->tenantId, $query->orderId) === null) {
            throw AggregateNotFoundException::order($query->orderId);
        }

        $path = rtrim($this->invoiceDirectory, '/') . '/invoice-' . $query->orderId . '.pdf';
        if (!is_file($path)) {
            throw InvoiceNotReadyException::forOrder($query->orderId);
        }

        return (string) file_get_contents($path);
    }
}
