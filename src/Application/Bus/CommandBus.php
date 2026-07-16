<?php

declare(strict_types=1);

namespace OrderHub\Application\Bus;

use OrderHub\Application\Bus\Exceptions\NoHandlerRegisteredException;

/**
 * Minimal command bus: maps a command class to a single handler callable and
 * dispatches to it. Deliberately hand-rolled (no external package) to keep the
 * CQRS wiring explicit and inspectable.
 */
final class CommandBus
{
    /** @var array<class-string<Command>, callable> */
    private array $handlers = [];

    /**
     * @param class-string<Command> $commandClass
     * @param callable $handler invokable handler for that command
     */
    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(Command $command): mixed
    {
        $class = $command::class;
        if (!isset($this->handlers[$class])) {
            throw NoHandlerRegisteredException::forMessage($class);
        }

        return ($this->handlers[$class])($command);
    }
}
