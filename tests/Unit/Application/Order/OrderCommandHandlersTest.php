<?php

declare(strict_types=1);

namespace OrderHub\Tests\Unit\Application\Order;

use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateOrder\CreateOrderHandler;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Command\PayOrder\PayOrderHandler;
use OrderHub\Application\Command\ShipOrder\ShipOrderCommand;
use OrderHub\Application\Command\ShipOrder\ShipOrderHandler;
use OrderHub\Application\EventBus\EventBus;
use OrderHub\Application\Exceptions\AuthorizationException;
use OrderHub\Application\Order\OrderRepository;
use OrderHub\Domain\Order\OrderId;
use OrderHub\Domain\Order\OrderStatus;
use OrderHub\Domain\Product\Product;
use OrderHub\Domain\Product\ProductId;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Shared\Money;
use OrderHub\Infrastructure\Persistence\InMemoryEventStore;
use OrderHub\Tests\Support\FrozenClock;
use OrderHub\Tests\Support\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Exercises the write-side handlers against in-memory ports (no DB), verifying
 * the aggregate is persisted and reloaded from events across commands, and that
 * the tenant guard blocks cross-tenant writes.
 */
final class OrderCommandHandlersTest extends TestCase
{
    private OrderRepository $orders;
    private InMemoryProductRepository $products;
    private FrozenClock $clock;
    private string $tenantId;
    private string $productId;

    protected function setUp(): void
    {
        $this->orders = new OrderRepository(new InMemoryEventStore(), new EventBus());
        $this->products = new InMemoryProductRepository();
        $this->clock = new FrozenClock();
        $this->tenantId = Uuid::uuid4()->toString();

        $this->productId = ProductId::generate()->value;
        $this->products->save(new Product(
            ProductId::fromString($this->productId),
            $this->tenantId,
            'Book',
            Money::ofCents(3000),
            10,
        ));
    }

    public function testCreatePayShipRoundTripsThroughTheEventStore(): void
    {
        $createId = (new CreateOrderHandler($this->orders, $this->products, $this->clock))(
            new CreateOrderCommand($this->tenantId, 'Dev', 'dev@example.com', [['productId' => $this->productId, 'quantity' => 2]]),
        );

        (new PayOrderHandler($this->orders, $this->clock))(new PayOrderCommand($this->tenantId, $createId, 'pix'));
        (new ShipOrderHandler($this->orders, $this->clock))(new ShipOrderCommand($this->tenantId, $createId, 'BR777'));

        $order = $this->orders->get(OrderId::fromString($createId));
        self::assertSame(OrderStatus::Shipped, $order->status());
        self::assertSame(6000, $order->totalAmount()->cents);
        self::assertSame(3, $order->version());
    }

    public function testCreateOrderFailsForUnknownProduct(): void
    {
        $this->expectException(AggregateNotFoundException::class);
        (new CreateOrderHandler($this->orders, $this->products, $this->clock))(
            new CreateOrderCommand($this->tenantId, 'Dev', 'dev@example.com', [['productId' => Uuid::uuid4()->toString(), 'quantity' => 1]]),
        );
    }

    public function testAnotherTenantCannotPayThisTenantsOrder(): void
    {
        $createId = (new CreateOrderHandler($this->orders, $this->products, $this->clock))(
            new CreateOrderCommand($this->tenantId, 'Dev', 'dev@example.com', [['productId' => $this->productId, 'quantity' => 1]]),
        );

        $this->expectException(AuthorizationException::class);
        (new PayOrderHandler($this->orders, $this->clock))(
            new PayOrderCommand(Uuid::uuid4()->toString(), $createId, 'pix'),
        );
    }
}
