<?php

declare(strict_types=1);

namespace OrderHub\Interface\Cli\Command;

use OrderHub\Infrastructure\Bootstrap\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Long-running worker that consumes the job queue. Handles SIGTERM/SIGINT (when
 * pcntl is available) so container shutdowns drain gracefully.
 */
#[AsCommand(name: 'queue:work', description: 'Process jobs from the queue continuously.')]
final class QueueWorkCommand extends Command
{
    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Stop after N jobs (0 = run forever)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $worker = $this->container->worker();

        if (\function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $stop = static function () use ($worker, $io): void {
                $io->writeln('Received stop signal, finishing current job...');
                $worker->stop();
            };
            pcntl_signal(\SIGTERM, $stop);
            pcntl_signal(\SIGINT, $stop);
        }

        $maxJobs = (int) $input->getOption('max-jobs');
        $io->writeln('OrderHub worker started. Waiting for jobs...');
        $worker->run($maxJobs);
        $io->writeln('Worker stopped.');

        return Command::SUCCESS;
    }
}
