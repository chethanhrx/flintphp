<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Events;

/**
 * A synchronous event dispatcher.
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        $eventClass = $event::class;

        // Take a stable snapshot of the listeners for this event class.
        // This ensures that if a listener registers another listener for the same
        // event class during execution, the new listener will not execute during
        // the current dispatch loop, preventing accidental infinite loops.
        $listeners = $this->listeners[$eventClass] ?? [];

        foreach ($listeners as $listener) {
            // Exceptions are intentionally allowed to propagate naturally.
            // If a Throwable is thrown, subsequent listeners will not execute.
            $listener($event);
        }

        // Return the exact same object instance.
        return $event;
    }
}
