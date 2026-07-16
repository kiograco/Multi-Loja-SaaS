<?php

declare(strict_types=1);

namespace OrderHub\Interface\Cli\Command;

use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;
use OrderHub\Domain\User\UserId;
use OrderHub\Infrastructure\Bootstrap\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'tenant:create', description: 'Create a store (tenant) for a user, creating the user if needed.')]
final class TenantCreateCommand extends Command
{
    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Store name')
            ->addOption('owner-email', null, InputOption::VALUE_REQUIRED, 'Owner user e-mail')
            ->addOption('owner-password', null, InputOption::VALUE_REQUIRED, 'Owner password (generated if omitted for a new user)')
            ->addOption('webhook-url', null, InputOption::VALUE_REQUIRED, 'Optional webhook URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->requireOption($input, 'name');
        $email = $this->requireOption($input, 'owner-email');
        $webhook = $input->getOption('webhook-url');
        $webhook = \is_string($webhook) ? $webhook : null;

        $users = $this->container->userRepository();
        $existing = $users->findByEmail($email);

        $generatedPassword = null;
        if ($existing === null) {
            $password = $input->getOption('owner-password');
            if (!\is_string($password) || $password === '') {
                $generatedPassword = bin2hex(random_bytes(6));
                $password = $generatedPassword;
            }
            $userId = $this->container->commandBus()->dispatch(new RegisterUserCommand($email, $password));
            $io->writeln(\sprintf('Created user <info>%s</info> (%s).', $email, $userId));
        } else {
            $userId = $existing->id->value;
        }

        // Sanity: userId must be a valid id.
        UserId::fromString((string) $userId);

        $tenantId = $this->container->commandBus()->dispatch(new CreateTenantCommand((string) $userId, $name, $webhook));

        $io->success(\sprintf('Created tenant "%s" with id %s.', $name, $tenantId));
        if ($generatedPassword !== null) {
            $io->warning(\sprintf('Generated password for %s: %s (store it now, it will not be shown again).', $email, $generatedPassword));
        }

        return Command::SUCCESS;
    }

    private function requireOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('The --%s option is required.', $name));
        }

        return $value;
    }
}
