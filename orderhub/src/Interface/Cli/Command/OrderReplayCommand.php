<?php

declare(strict_types=1);

namespace OrderHub\Interface\Cli\Command;

use OrderHub\Domain\Order\Order;
use OrderHub\Domain\Order\OrderItem;
use OrderHub\Infrastructure\Bootstrap\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rebuilds an order purely from the event store and prints its state — no
 * projection is read. This proves event sourcing works independently of any
 * read model.
 */
#[AsCommand(name: 'order:replay', description: 'Reconstruct and print an order from the raw event store.')]
final class OrderReplayCommand extends Command
{
    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Order id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = $input->getOption('id');
        if (!\is_string($id) || $id === '') {
            $io->error('The --id option is required.');

            return Command::INVALID;
        }

        $stream = $this->container->eventStore()->load($id);
        if ($stream->isEmpty()) {
            $io->error(\sprintf('No events found for order %s.', $id));

            return Command::FAILURE;
        }

        $order = Order::reconstituteFrom($stream);

        $io->section('Order reconstructed from ' . $stream->count() . ' event(s)');
        $io->definitionList(
            ['Order id' => $order->id()->value],
            ['Tenant' => $order->tenantId()],
            ['Customer' => $order->customerName() . ' <' . $order->customerEmail() . '>'],
            ['Status' => $order->status()->value],
            ['Version' => (string) $order->version()],
            ['Total' => $order->totalAmount()->currency . ' ' . $order->totalAmount()->toDecimal()],
        );

        $io->text('Items:');
        foreach ($order->items() as $item) {
            /** @var OrderItem $item */
            $io->text(\sprintf(
                '  - %dx %s @ %s %s',
                $item->quantity,
                $item->productName,
                $item->unitPrice->currency,
                $item->unitPrice->toDecimal(),
            ));
        }

        $io->text('Event sequence:');
        foreach ($stream as $event) {
            $io->text('  - ' . $event->eventType() . ' @ ' . $event->occurredAt()->format('c'));
        }

        return Command::SUCCESS;
    }
}
