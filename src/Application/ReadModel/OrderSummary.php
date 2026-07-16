<?php

declare(strict_types=1);

namespace OrderHub\Application\ReadModel;

use DateTimeImmutable;

/**
 * Read model for a single order. Denormalised and always current — the API and
 * dashboard read this instead of replaying events, so reads never touch the
 * event store.
 */
final readonly class OrderSummary
{
    /**
     * @param list<array{productId: string, productName: string, unitPriceCents: int, currency: string, quantity: int}> $items
     */
    public function __construct(
        public string $orderId,
        public string $tenantId,
        public string $customerName,
        public string $customerEmail,
        public string $status,
        public int $totalCents,
        public string $currency,
        public array $items,
        public ?string $trackingCode,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'tenantId' => $this->tenantId,
            'customerName' => $this->customerName,
            'customerEmail' => $this->customerEmail,
            'status' => $this->status,
            'total' => number_format($this->totalCents / 100, 2, '.', ''),
            'totalCents' => $this->totalCents,
            'currency' => $this->currency,
            'items' => $this->items,
            'trackingCode' => $this->trackingCode,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
