<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Queue\Jobs;

use OrderHub\Application\Queue\JobHandler;
use OrderHub\Application\Queue\JobType;
use OrderHub\Application\Queue\QueuedJob;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Application\Support\Logger;
use OrderHub\Infrastructure\Invoice\InvoicePdfRenderer;

final class GenerateInvoicePdfJob implements JobHandler
{
    public function __construct(
        private readonly OrderSummaryReadStore $orders,
        private readonly InvoicePdfRenderer $renderer,
        private readonly string $outputDirectory,
        private readonly Logger $logger,
    ) {
    }

    public function type(): string
    {
        return JobType::GENERATE_INVOICE_PDF;
    }

    public function handle(QueuedJob $job): void
    {
        $orderId = (string) $job->payload['orderId'];
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return;
        }

        if (!is_dir($this->outputDirectory) && !mkdir($this->outputDirectory, 0775, true) && !is_dir($this->outputDirectory)) {
            throw new \RuntimeException('Cannot create invoice directory: ' . $this->outputDirectory);
        }

        $path = rtrim($this->outputDirectory, '/') . '/invoice-' . $orderId . '.pdf';
        file_put_contents($path, $this->renderer->render($order));

        $this->logger->info('Invoice PDF generated', ['orderId' => $orderId, 'path' => $path]);
    }
}
