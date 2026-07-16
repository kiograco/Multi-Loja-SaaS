<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Queue\Jobs;

use OrderHub\Application\Queue\JobHandler;
use OrderHub\Application\Queue\JobType;
use OrderHub\Application\Queue\QueuedJob;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;

/**
 * Abates each ordered product's stock. Idempotency is guaranteed by the worker's
 * processed_jobs ledger (job id = "DecrementStock:<orderId>"), so the decrement
 * runs at most once per order even if the job is redelivered.
 */
final class DecrementStockJob implements JobHandler
{
    public function __construct(
        private readonly OrderSummaryReadStore $orders,
        private readonly ProductRepository $products,
    ) {
    }

    public function type(): string
    {
        return JobType::DECREMENT_STOCK;
    }

    public function handle(QueuedJob $job): void
    {
        $orderId = (string) $job->payload['orderId'];
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return;
        }

        foreach ($order->items as $item) {
            $product = $this->products->findById($order->tenantId, ProductId::fromString($item['productId']));
            if ($product === null) {
                continue;
            }
            // Clamp so a stock shortfall never aborts the whole payment side effect.
            $product->decrementStock(min($item['quantity'], $product->stockQuantity()));
            $this->products->save($product);
        }
    }
}
