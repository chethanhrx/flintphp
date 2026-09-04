<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Events;

use FlintPHP\Framework\Events\EventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// Dummy events for exact matching tests
class BaseEvent {}
class ChildEvent extends BaseEvent {}
interface SomeInterface {}
class ImplementsInterfaceEvent implements SomeInterface {}
class MutableEvent {
    public int $counter = 0;
}

#[CoversClass(EventDispatcher::class)]
final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    #[Test]
    public function it_returns_the_same_event_instance(): void
    {
        $event = new BaseEvent();
        $result = $this->dispatcher->dispatch($event);
        
        $this->assertSame($event, $result);
    }

    #[Test]
    public function dispatching_with_no_listeners_is_safe(): void
    {
        $event = new BaseEvent();
        $this->assertSame($event, $this->dispatcher->dispatch($event));
    }

    #[Test]
    public function it_executes_registered_listeners_in_order(): void
    {
        $event = new BaseEvent();
        $executionOrder = [];

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'A';
        });
        
        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'B';
        });

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'C';
        });

        $this->dispatcher->dispatch($event);

        $this->assertSame(['A', 'B', 'C'], $executionOrder);
    }

    #[Test]
    public function listener_receives_exact_event_instance(): void
    {
        $event = new BaseEvent();
        $receivedEvent = null;

        $this->dispatcher->listen(BaseEvent::class, function ($e) use (&$receivedEvent) {
            $receivedEvent = $e;
        });

        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $receivedEvent);
    }

    #[Test]
    public function duplicate_registration_executes_multiple_times(): void
    {
        $event = new BaseEvent();
        $counter = 0;
        
        $listener = function () use (&$counter) {
            $counter++;
        };

        $this->dispatcher->listen(BaseEvent::class, $listener);
        $this->dispatcher->listen(BaseEvent::class, $listener);

        $this->dispatcher->dispatch($event);

        $this->assertSame(2, $counter);
    }

    #[Test]
    public function exact_matching_ignores_parent_classes(): void
    {
        $event = new ChildEvent();
        $executedBase = false;
        $executedChild = false;

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executedBase) {
            $executedBase = true;
        });
        
        $this->dispatcher->listen(ChildEvent::class, function () use (&$executedChild) {
            $executedChild = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertFalse($executedBase, 'BaseEvent listener should not execute for ChildEvent.');
        $this->assertTrue($executedChild, 'ChildEvent listener should execute.');
    }

    #[Test]
    public function exact_matching_ignores_interfaces(): void
    {
        $event = new ImplementsInterfaceEvent();
        $executedInterface = false;

        $this->dispatcher->listen(SomeInterface::class, function () use (&$executedInterface) {
            $executedInterface = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertFalse($executedInterface, 'Interface listener should not execute.');
    }

    #[Test]
    public function multiple_dispatchers_are_isolated(): void
    {
        $dispatcherB = new EventDispatcher();
        $event = new BaseEvent();
        
        $executedA = false;
        $executedB = false;

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executedA) {
            $executedA = true;
        });
        
        $dispatcherB->listen(BaseEvent::class, function () use (&$executedB) {
            $executedB = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertTrue($executedA);
        $this->assertFalse($executedB);
    }

    #[Test]
    public function exceptions_propagate_and_halt_subsequent_listeners(): void
    {
        $event = new BaseEvent();
        $executionOrder = [];

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'A';
        });
        
        $this->dispatcher->listen(BaseEvent::class, function () {
            throw new RuntimeException('Listener failed');
        });

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'C';
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Listener failed');

        try {
            $this->dispatcher->dispatch($event);
        } finally {
            $this->assertSame(['A'], $executionOrder, 'Listener C should not execute.');
        }
    }

    #[Test]
    public function reentrant_dispatch_works_correctly(): void
    {
        $executionOrder = [];

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'Base-Start';
            $this->dispatcher->dispatch(new ChildEvent());
            $executionOrder[] = 'Base-End';
        });

        $this->dispatcher->listen(ChildEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'Child';
        });

        $this->dispatcher->dispatch(new BaseEvent());

        $this->assertSame([
            'Base-Start',
            'Child',
            'Base-End'
        ], $executionOrder);
    }

    #[Test]
    public function registering_listener_during_dispatch_does_not_execute_in_current_loop(): void
    {
        $event = new BaseEvent();
        $executionOrder = [];

        $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
            $executionOrder[] = 'A';
            
            // Register a new listener for the SAME event
            $this->dispatcher->listen(BaseEvent::class, function () use (&$executionOrder) {
                $executionOrder[] = 'B (New)';
            });
        });

        // First dispatch
        $this->dispatcher->dispatch($event);
        
        // Listener B should NOT have executed during the first dispatch
        $this->assertSame(['A'], $executionOrder);

        // Second dispatch
        $this->dispatcher->dispatch($event);
        
        // Listener B should execute during the second dispatch
        // Note: Listener A is executed twice. The second execution registers B again,
        // but it won't execute until a third dispatch.
        $this->assertSame(['A', 'A', 'B (New)'], $executionOrder);
    }
    
    #[Test]
    public function listeners_can_mutate_mutable_events(): void
    {
        $event = new MutableEvent();
        
        $this->dispatcher->listen(MutableEvent::class, function (MutableEvent $e) {
            $e->counter += 10;
        });

        $result = $this->dispatcher->dispatch($event);
        
        $this->assertSame(10, $event->counter);
        $this->assertSame(10, $result->counter);
    }
    
    #[Test]
    public function invokable_objects_are_supported(): void
    {
        $event = new BaseEvent();
        $invokable = new class {
            public bool $executed = false;
            public function __invoke(BaseEvent $e): void {
                $this->executed = true;
            }
        };

        $this->dispatcher->listen(BaseEvent::class, $invokable);
        $this->dispatcher->dispatch($event);

        $this->assertTrue($invokable->executed);
    }
}
