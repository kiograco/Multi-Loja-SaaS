<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Bootstrap;

use OrderHub\Application\Auth\LoginService;
use OrderHub\Application\Auth\TokenIssuer;
use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CancelOrder\CancelOrderCommand;
use OrderHub\Application\Command\CancelOrder\CancelOrderHandler;
use OrderHub\Application\Command\CreateOrder\CreateOrderCommand;
use OrderHub\Application\Command\CreateOrder\CreateOrderHandler;
use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\CreateProduct\CreateProductHandler;
use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\CreateTenant\CreateTenantHandler;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Command\PayOrder\PayOrderHandler;
use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;
use OrderHub\Application\Command\RegisterUser\RegisterUserHandler;
use OrderHub\Application\Command\ShipOrder\ShipOrderCommand;
use OrderHub\Application\Command\ShipOrder\ShipOrderHandler;
use OrderHub\Application\Command\UpdateProduct\UpdateProductCommand;
use OrderHub\Application\Command\UpdateProduct\UpdateProductHandler;
use OrderHub\Application\EventBus\EventBus;
use OrderHub\Application\EventBus\OrderSideEffectsSubscriber;
use OrderHub\Application\Order\OrderRepository;
use OrderHub\Application\Projector\DailySalesProjector;
use OrderHub\Application\Projector\OrderSummaryProjector;
use OrderHub\Application\Projector\ProjectionRebuilder;
use OrderHub\Application\Projector\TopProductsProjector;
use OrderHub\Application\Query\GetDashboardSummary\GetDashboardSummaryHandler;
use OrderHub\Application\Query\GetDashboardSummary\GetDashboardSummaryQuery;
use OrderHub\Application\Query\GetOrderSummary\GetOrderSummaryHandler;
use OrderHub\Application\Query\GetOrderSummary\GetOrderSummaryQuery;
use OrderHub\Application\Query\ListOrders\ListOrdersHandler;
use OrderHub\Application\Query\ListOrders\ListOrdersQuery;
use OrderHub\Application\Query\ListProducts\ListProductsHandler;
use OrderHub\Application\Query\ListProducts\ListProductsQuery;
use OrderHub\Application\Queue\JobQueue;
use OrderHub\Application\Queue\ProcessedJobLedger;
use OrderHub\Application\Queue\Worker;
use OrderHub\Application\ReadModel\DailySalesReadStore;
use OrderHub\Application\ReadModel\OrderSummaryReadStore;
use OrderHub\Application\ReadModel\TopProductsReadStore;
use OrderHub\Application\Support\Logger;
use OrderHub\Application\Webhook\WebhookClient;
use OrderHub\Domain\Order\Events\OrderEventFactory;
use OrderHub\Domain\Product\ProductRepository;
use OrderHub\Domain\Shared\Clock;
use OrderHub\Domain\Shared\EventStoreInterface;
use OrderHub\Domain\Shared\SystemClock;
use OrderHub\Domain\Tenant\TenantRepository;
use OrderHub\Domain\User\UserRepository;
use OrderHub\Infrastructure\Auth\FirebaseJwtTokenIssuer;
use OrderHub\Infrastructure\Config\Env;
use OrderHub\Infrastructure\Http\RateLimiter;
use OrderHub\Infrastructure\Invoice\InvoicePdfRenderer;
use OrderHub\Infrastructure\Logging\StreamLogger;
use OrderHub\Infrastructure\Persistence\Database;
use OrderHub\Infrastructure\Persistence\PostgresEventStore;
use OrderHub\Infrastructure\Persistence\PostgresProcessedJobLedger;
use OrderHub\Infrastructure\Persistence\PostgresProductRepository;
use OrderHub\Infrastructure\Persistence\PostgresTenantRepository;
use OrderHub\Infrastructure\Persistence\PostgresUserRepository;
use OrderHub\Infrastructure\Persistence\ReadModel\PostgresDailySalesStore;
use OrderHub\Infrastructure\Persistence\ReadModel\PostgresOrderSummaryStore;
use OrderHub\Infrastructure\Persistence\ReadModel\PostgresTopProductsStore;
use OrderHub\Infrastructure\Queue\Jobs\DecrementStockJob;
use OrderHub\Infrastructure\Queue\Jobs\DispatchWebhookJob;
use OrderHub\Infrastructure\Queue\Jobs\GenerateInvoicePdfJob;
use OrderHub\Infrastructure\Queue\Jobs\SendOrderConfirmationEmailJob;
use OrderHub\Infrastructure\Queue\RedisJobQueue;
use OrderHub\Infrastructure\Webhook\CurlWebhookClient;
use Redis;

/**
 * Composition root. Builds and memoises the object graph, wires CQRS handlers,
 * event subscribers and projectors, and exposes the buses the interface layer
 * uses. Dependencies can be overridden via set() before first use, which is how
 * tests swap in fakes (in-memory queue, stub webhook client, frozen clock).
 */
final class Container
{
    /** @var array<string, object> */
    private array $services = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @param callable(): T $factory
     * @return T
     */
    private function shared(string $id, callable $factory): object
    {
        /** @var T */
        return $this->services[$id] ??= $factory();
    }

    public function clock(): Clock
    {
        /** @var Clock */
        return $this->shared(Clock::class, static fn (): Clock => new SystemClock());
    }

    public function logger(): Logger
    {
        /** @var Logger */
        return $this->shared(Logger::class, static fn (): Logger => new StreamLogger());
    }

    public function database(): Database
    {
        /** @var Database */
        return $this->shared(Database::class, static fn (): Database => Database::fromEnv());
    }

    public function redis(): Redis
    {
        /** @var Redis */
        return $this->shared(Redis::class, static function (): Redis {
            $redis = new Redis();
            $redis->connect(Env::get('REDIS_HOST', '127.0.0.1'), Env::int('REDIS_PORT', 6379));

            return $redis;
        });
    }

    public function eventStore(): EventStoreInterface
    {
        /** @var EventStoreInterface */
        return $this->shared(EventStoreInterface::class, fn (): EventStoreInterface => new PostgresEventStore(
            $this->database(),
            new OrderEventFactory(),
        ));
    }

    public function orderSummaryStore(): OrderSummaryReadStore
    {
        /** @var OrderSummaryReadStore */
        return $this->shared(OrderSummaryReadStore::class, fn (): OrderSummaryReadStore => new PostgresOrderSummaryStore($this->database()));
    }

    public function dailySalesStore(): DailySalesReadStore
    {
        /** @var DailySalesReadStore */
        return $this->shared(DailySalesReadStore::class, fn (): DailySalesReadStore => new PostgresDailySalesStore($this->database()));
    }

    public function topProductsStore(): TopProductsReadStore
    {
        /** @var TopProductsReadStore */
        return $this->shared(TopProductsReadStore::class, fn (): TopProductsReadStore => new PostgresTopProductsStore($this->database()));
    }

    public function productRepository(): ProductRepository
    {
        /** @var ProductRepository */
        return $this->shared(ProductRepository::class, fn (): ProductRepository => new PostgresProductRepository($this->database()));
    }

    public function tenantRepository(): TenantRepository
    {
        /** @var TenantRepository */
        return $this->shared(TenantRepository::class, fn (): TenantRepository => new PostgresTenantRepository($this->database()));
    }

    public function userRepository(): UserRepository
    {
        /** @var UserRepository */
        return $this->shared(UserRepository::class, fn (): UserRepository => new PostgresUserRepository($this->database()));
    }

    public function webhookClient(): WebhookClient
    {
        /** @var WebhookClient */
        return $this->shared(WebhookClient::class, static fn (): WebhookClient => new CurlWebhookClient());
    }

    public function jobQueue(): JobQueue
    {
        /** @var JobQueue */
        return $this->shared(JobQueue::class, fn (): JobQueue => new RedisJobQueue($this->redis()));
    }

    public function processedJobLedger(): ProcessedJobLedger
    {
        /** @var ProcessedJobLedger */
        return $this->shared(ProcessedJobLedger::class, fn (): ProcessedJobLedger => new PostgresProcessedJobLedger($this->database()));
    }

    public function tokenIssuer(): TokenIssuer
    {
        /** @var TokenIssuer */
        return $this->shared(TokenIssuer::class, static fn (): TokenIssuer => new FirebaseJwtTokenIssuer(Env::get('JWT_SECRET')));
    }

    public function rateLimiter(): RateLimiter
    {
        /** @var RateLimiter */
        return $this->shared(RateLimiter::class, fn (): RateLimiter => new RateLimiter($this->redis(), Env::int('RATE_LIMIT_PER_MINUTE', 100)));
    }

    public function orderSummaryProjector(): OrderSummaryProjector
    {
        /** @var OrderSummaryProjector */
        return $this->shared(OrderSummaryProjector::class, fn (): OrderSummaryProjector => new OrderSummaryProjector($this->orderSummaryStore()));
    }

    public function dailySalesProjector(): DailySalesProjector
    {
        /** @var DailySalesProjector */
        return $this->shared(DailySalesProjector::class, fn (): DailySalesProjector => new DailySalesProjector($this->dailySalesStore(), $this->orderSummaryStore()));
    }

    public function topProductsProjector(): TopProductsProjector
    {
        /** @var TopProductsProjector */
        return $this->shared(TopProductsProjector::class, fn (): TopProductsProjector => new TopProductsProjector($this->topProductsStore(), $this->orderSummaryStore()));
    }

    public function eventBus(): EventBus
    {
        /** @var EventBus */
        return $this->shared(EventBus::class, function (): EventBus {
            $bus = new EventBus();
            // Order matters: summary first so aggregate projectors can read it.
            $bus->subscribe($this->orderSummaryProjector());
            $bus->subscribe($this->dailySalesProjector());
            $bus->subscribe($this->topProductsProjector());
            // Side effects (async) enqueue only — the slow work happens in the worker.
            $bus->subscribe(new OrderSideEffectsSubscriber($this->jobQueue()));

            return $bus;
        });
    }

    public function orderRepository(): OrderRepository
    {
        /** @var OrderRepository */
        return $this->shared(OrderRepository::class, fn (): OrderRepository => new OrderRepository($this->eventStore(), $this->eventBus()));
    }

    public function loginService(): LoginService
    {
        /** @var LoginService */
        return $this->shared(LoginService::class, fn (): LoginService => new LoginService(
            $this->userRepository(),
            $this->tenantRepository(),
            $this->tokenIssuer(),
            $this->clock(),
            Env::int('JWT_TTL', 3600),
        ));
    }

    public function commandBus(): CommandBus
    {
        /** @var CommandBus */
        return $this->shared(CommandBus::class, function (): CommandBus {
            $bus = new CommandBus();
            $bus->register(RegisterUserCommand::class, new RegisterUserHandler($this->userRepository()));
            $bus->register(CreateTenantCommand::class, new CreateTenantHandler($this->tenantRepository(), $this->clock()));
            $bus->register(CreateProductCommand::class, new CreateProductHandler($this->productRepository()));
            $bus->register(UpdateProductCommand::class, new UpdateProductHandler($this->productRepository()));
            $bus->register(CreateOrderCommand::class, new CreateOrderHandler($this->orderRepository(), $this->productRepository(), $this->clock()));
            $bus->register(PayOrderCommand::class, new PayOrderHandler($this->orderRepository(), $this->clock()));
            $bus->register(ShipOrderCommand::class, new ShipOrderHandler($this->orderRepository(), $this->clock()));
            $bus->register(CancelOrderCommand::class, new CancelOrderHandler($this->orderRepository(), $this->clock()));

            return $bus;
        });
    }

    public function queryBus(): QueryBus
    {
        /** @var QueryBus */
        return $this->shared(QueryBus::class, function (): QueryBus {
            $bus = new QueryBus();
            $bus->register(GetOrderSummaryQuery::class, new GetOrderSummaryHandler($this->orderSummaryStore()));
            $bus->register(ListOrdersQuery::class, new ListOrdersHandler($this->orderSummaryStore()));
            $bus->register(ListProductsQuery::class, new ListProductsHandler($this->productRepository()));
            $bus->register(GetDashboardSummaryQuery::class, new GetDashboardSummaryHandler($this->dailySalesStore(), $this->topProductsStore()));

            return $bus;
        });
    }

    public function projectionRebuilder(): ProjectionRebuilder
    {
        /** @var ProjectionRebuilder */
        return $this->shared(ProjectionRebuilder::class, function (): ProjectionRebuilder {
            $rebuilder = new ProjectionRebuilder($this->eventStore());
            $rebuilder->register('order_summary', fn () => $this->orderSummaryStore()->truncate(), [$this->orderSummaryProjector()]);
            $rebuilder->register('daily_sales', fn () => $this->dailySalesStore()->truncate(), [$this->dailySalesProjector()]);
            $rebuilder->register('top_products', fn () => $this->topProductsStore()->truncate(), [$this->topProductsProjector()]);

            return $rebuilder;
        });
    }

    public function invoiceDirectory(): string
    {
        return \dirname(__DIR__, 3) . '/var/invoices';
    }

    public function worker(): Worker
    {
        /** @var Worker */
        return $this->shared(Worker::class, function (): Worker {
            $worker = new Worker($this->jobQueue(), $this->processedJobLedger(), $this->logger());
            $worker->registerHandler(new DecrementStockJob($this->orderSummaryStore(), $this->productRepository()));
            $worker->registerHandler(new GenerateInvoicePdfJob($this->orderSummaryStore(), new InvoicePdfRenderer(), $this->invoiceDirectory(), $this->logger()));
            $worker->registerHandler(new SendOrderConfirmationEmailJob($this->orderSummaryStore(), $this->logger()));
            $worker->registerHandler(new DispatchWebhookJob($this->orderSummaryStore(), $this->tenantRepository(), $this->webhookClient()));

            return $worker;
        });
    }
}
