<?php

declare(strict_types=1);

namespace OrderHub\Application\Bus;

use OrderHub\Application\Bus\Exceptions\NoHandlerRegisteredException;

final class QueryBus
{
    /** @var array<class-string<Query>, callable> */
    private array $handlers = [];

    /**
     * @param class-string<Query> $queryClass
     * @param callable $handler invokable handler for that query
     */
    public function register(string $queryClass, callable $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    public function ask(Query $query): mixed
    {
        $class = $query::class;
        if (!isset($this->handlers[$class])) {
            throw NoHandlerRegisteredException::forMessage($class);
        }

        return ($this->handlers[$class])($query);
    }
}
