<?php

declare(strict_types=1);

namespace OrderHub\Interface\Cli\Command;

use OrderHub\Application\Command\CreateProduct\CreateProductCommand;
use OrderHub\Application\Command\CreateTenant\CreateTenantCommand;
use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;
use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Infrastructure\Bootstrap\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot demo seed so a fresh checkout has a login, a store and some products
 * to exercise the API immediately (referenced by the README quick start).
 */
#[AsCommand(name: 'seed:demo', description: 'Seed a demo user, tenant and products.')]
final class SeedDemoCommand extends Command
{
    private const EMAIL = 'demo@orderhub.test';
    private const PASSWORD = 'password';

    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bus = $this->container->commandBus();

        $users = $this->container->userRepository();
        $existing = $users->findByEmail(self::EMAIL);
        if ($existing !== null) {
            $io->warning('Demo user already exists; skipping seed to stay idempotent.');

            return Command::SUCCESS;
        }

        try {
            $bus->dispatch(new RegisterUserCommand(self::EMAIL, self::PASSWORD));
        } catch (ConflictException) {
            // Race with another seed — safe to continue.
        }

        $user = $users->findByEmail(self::EMAIL);
        if ($user === null) {
            $io->error('Failed to create demo user.');

            return Command::FAILURE;
        }

        $tenantId = $bus->dispatch(new CreateTenantCommand($user->id->value, 'Loja Demo'));

        $products = [
            ['Camiseta OrderHub', 7900, 100],
            ['Caneca DDD', 4500, 50],
            ['Adesivo Event Sourcing', 900, 500],
        ];
        foreach ($products as [$name, $priceCents, $stock]) {
            $bus->dispatch(new CreateProductCommand((string) $tenantId, $name, $priceCents, $stock));
        }

        $io->success('Demo data seeded.');
        $io->definitionList(
            ['E-mail' => self::EMAIL],
            ['Password' => self::PASSWORD],
            ['Tenant id' => (string) $tenantId],
        );
        $io->text('Login with:');
        $io->text(\sprintf(
            "curl -s -X POST http://localhost:8080/api/v1/auth/login -H 'Content-Type: application/json' -d '%s'",
            json_encode(['email' => self::EMAIL, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR),
        ));

        return Command::SUCCESS;
    }
}
