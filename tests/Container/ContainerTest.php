<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Container;

use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Container\ContainerException;
use FlintPHP\Framework\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // ---------------------------------------------------------------
    // Explicit Bindings
    // ---------------------------------------------------------------

    #[Test]
    public function it_stores_and_retrieves_explicit_values(): void
    {
        $this->container->set('config', ['debug' => true]);

        $this->assertTrue($this->container->has('config'));
        $this->assertSame(['debug' => true], $this->container->get('config'));
    }

    #[Test]
    public function it_resolves_closures_and_injects_container(): void
    {
        $this->container->set('config', ['debug' => true]);
        $this->container->set('service', function (ContainerInterface $c) {
            return (object) ['config' => $c->get('config')];
        });

        $service = $this->container->get('service');

        $this->assertTrue($service->config['debug']);
    }

    #[Test]
    public function transient_bindings_return_new_instances(): void
    {
        $this->container->set('random', function () {
            return new \stdClass();
        });

        $instance1 = $this->container->get('random');
        $instance2 = $this->container->get('random');

        $this->assertNotSame($instance1, $instance2);
    }

    #[Test]
    public function singletons_return_the_same_instance(): void
    {
        $this->container->singleton('shared', function () {
            return new \stdClass();
        });

        $instance1 = $this->container->get('shared');
        $instance2 = $this->container->get('shared');

        $this->assertSame($instance1, $instance2);
    }

    #[Test]
    public function it_resolves_aliases(): void
    {
        $this->container->set('database', 'mysql_connection');
        $this->container->bind('db', 'database');

        $this->assertSame('mysql_connection', $this->container->get('db'));
    }

    #[Test]
    public function container_injects_itself(): void
    {
        $this->assertSame($this->container, $this->container->get(Container::class));
        $this->assertSame($this->container, $this->container->get(ContainerInterface::class));
    }

    // ---------------------------------------------------------------
    // Auto-wiring & Reflection
    // ---------------------------------------------------------------

    #[Test]
    public function it_autowires_concrete_classes_without_constructor(): void
    {
        $instance = $this->container->get(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    #[Test]
    public function it_autowires_concrete_classes_with_dependencies(): void
    {
        $instance = $this->container->get(ClassWithDependency::class);

        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->dependency);
    }

    #[Test]
    public function it_uses_default_values_for_scalar_parameters(): void
    {
        $instance = $this->container->get(ClassWithDefaultScalar::class);

        $this->assertSame(42, $instance->value);
    }

    #[Test]
    public function it_allows_null_for_nullable_scalar_parameters_without_defaults(): void
    {
        $instance = $this->container->get(ClassWithNullableScalar::class);

        $this->assertNull($instance->value);
    }

    // ---------------------------------------------------------------
    // Exceptions & Failure States
    // ---------------------------------------------------------------

    #[Test]
    public function it_throws_when_resolving_unbound_interfaces(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('not bound and does not exist');

        $this->container->get(SomeInterface::class);
    }

    #[Test]
    public function it_throws_on_unresolvable_scalar_parameters(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Unresolvable dependency');

        $this->container->get(ClassWithUnresolvableScalar::class);
    }

    #[Test]
    public function it_throws_on_circular_dependencies(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->get(CircularA::class);
    }

    #[Test]
    public function it_throws_on_circular_alias_a_b_a(): void
    {
        $this->container->bind('A', 'B');
        $this->container->bind('B', 'A');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular alias detected: A -> B -> A');

        $this->container->get('A');
    }

    #[Test]
    public function it_throws_on_circular_alias_a_b_c_a(): void
    {
        $this->container->bind('A', 'B');
        $this->container->bind('B', 'C');
        $this->container->bind('C', 'A');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular alias detected: A -> B -> C -> A');

        $this->container->get('A');
    }

    #[Test]
    public function it_throws_on_circular_alias_a_a(): void
    {
        $this->container->bind('A', 'A');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular alias detected: A -> A');

        $this->container->get('A');
    }

    #[Test]
    public function normal_multi_level_aliases_resolve_correctly(): void
    {
        $this->container->set('Z', 'final_value');
        $this->container->bind('A', 'B');
        $this->container->bind('B', 'C');
        $this->container->bind('C', 'Z');

        $this->assertSame('final_value', $this->container->get('A'));
    }
}

// ---------------------------------------------------------------
// Stubs for testing auto-wiring
// ---------------------------------------------------------------

class SimpleClass {}

class ClassWithDependency
{
    public function __construct(public SimpleClass $dependency) {}
}

class ClassWithDefaultScalar
{
    public function __construct(public int $value = 42) {}
}

class ClassWithNullableScalar
{
    public function __construct(public ?int $value) {}
}

class ClassWithUnresolvableScalar
{
    public function __construct(public int $value) {}
}

interface SomeInterface {}

class CircularA
{
    public function __construct(public CircularB $b) {}
}

class CircularB
{
    public function __construct(public CircularA $a) {}
}
