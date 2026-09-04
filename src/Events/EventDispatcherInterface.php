<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Events;

/**
 * Contract for a synchronous event dispatcher.
 */
interface EventDispatcherInterface
{
    /**
     * Register a listener for a specific event class.
     *
     * @param string   $eventClass The fully qualified class name of the event.
     * @param callable $listener   The callable to execute when the event is dispatched.
     */
    public function listen(string $eventClass, callable $listener): void;

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event object to dispatch.
     * @return object The same event object that was dispatched.
     */
    public function dispatch(object $event): object;
}
