<?php

declare(strict_types=1);

namespace OrderHub\Application\Projector;

use OrderHub\Application\EventBus\EventSubscriber;
use OrderHub\Domain\Shared\EventStoreInterface;

/**
 * Rebuilds a projection from scratch: wipe it, then replay the entire event
 * history (in global insertion order) through the matching projectors.
 *
 * This is the practical proof that read models are fully derived and disposable
 * — the core promise of event sourcing. Each named projection maps to a `reset`
 * callback and the projectors that feed it.
 */
final class ProjectionRebuilder
{
    /** @var array<string, array{reset: callable(): void, projectors: list<EventSubscriber>}> */
    private array $projections = [];

    public function __construct(private readonly EventStoreInterface $eventStore)
    {
    }

    /**
     * @param callable(): void $reset
     * @param list<EventSubscriber> $projectors
     */
    public function register(string $name, callable $reset, array $projectors): void
    {
        $this->projections[$name] = ['reset' => $reset, 'projectors' => $projectors];
    }

    /**
     * @return list<string> the projection names that were rebuilt
     */
    public function rebuild(string $name): array
    {
        return $this->run([$name => $this->requireProjection($name)]);
    }

    /**
     * @return list<string>
     */
    public function rebuildAll(): array
    {
        return $this->run($this->projections);
    }

    /**
     * @param array<string, array{reset: callable(): void, projectors: list<EventSubscriber>}> $selected
     *
     * @return list<string>
     */
    private function run(array $selected): array
    {
        foreach ($selected as $projection) {
            ($projection['reset'])();
        }

        // A single pass over history feeds every selected projector, so
        // cross-projection ordering (summary before aggregates) is preserved.
        foreach ($this->eventStore->loadAll() as $event) {
            foreach ($selected as $projection) {
                foreach ($projection['projectors'] as $projector) {
                    $projector->on($event);
                }
            }
        }

        return array_keys($selected);
    }

    /**
     * @return array{reset: callable(): void, projectors: list<EventSubscriber>}
     */
    private function requireProjection(string $name): array
    {
        if (!isset($this->projections[$name])) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown projection "%s". Known: %s.',
                $name,
                implode(', ', array_keys($this->projections)),
            ));
        }

        return $this->projections[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->projections);
    }
}
