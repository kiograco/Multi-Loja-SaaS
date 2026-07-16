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
 * Wipes a projection and rebuilds it from the full event history, proving read
 * models are fully derived and disposable. Use --name=all to rebuild every one.
 */
#[AsCommand(name: 'projection:rebuild', description: 'Rebuild a projection (read model) from the event store.')]
final class ProjectionRebuildCommand extends Command
{
    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Projection name, or "all"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rebuilder = $this->container->projectionRebuilder();

        $name = $input->getOption('name');
        if (!\is_string($name) || $name === '') {
            $io->error(\sprintf('The --name option is required. Known projections: %s, all.', implode(', ', $rebuilder->names())));

            return Command::INVALID;
        }

        $rebuilt = $name === 'all' ? $rebuilder->rebuildAll() : $rebuilder->rebuild($name);

        $io->success(\sprintf('Rebuilt projection(s): %s', implode(', ', $rebuilt)));

        return Command::SUCCESS;
    }
}
