<?php

declare(strict_types=1);

namespace OrderHub\Interface\Cli\Command;

use OrderHub\Infrastructure\Bootstrap\Container;
use OrderHub\Infrastructure\Persistence\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'migrate:up', description: 'Apply database migrations (idempotent).')]
final class MigrateUpCommand extends Command
{
    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $applied = (new MigrationRunner($this->container->database()))->up();
        $io->success(\sprintf('Applied %d migration file(s): %s', \count($applied), implode(', ', $applied) ?: '-'));

        return Command::SUCCESS;
    }
}
