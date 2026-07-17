<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\DeleteProduct;

use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;

/**
 * Hard delete: Product is plain CRUD (Seção 5.2), and OrderItem snapshots
 * name/price at order creation time (CreateOrderHandler), so removing a
 * product never affects the read model or event history of past orders.
 */
final class DeleteProductHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function __invoke(DeleteProductCommand $command): void
    {
        $productId = ProductId::fromString($command->productId);

        // Tenant-scoped lookup: deleting can never reach another tenant's product.
        if ($this->products->findById($command->tenantId, $productId) === null) {
            throw AggregateNotFoundException::product($command->productId);
        }

        $this->products->delete($command->tenantId, $productId);
    }
}
