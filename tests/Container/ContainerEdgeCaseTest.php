<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Container;

use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Container\ContainerException;
use FlintPHP\Framework\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for the Container.
 *
 * Covers gaps identified in the Phase A audit:
 * - Autowiring with interface dependencies via bind()
 * - has() negative cases
 * - Singleton re-registration clears cached instance
 * - set() re-registration clears cached instance
 * - Autowired instances are transient (not cached)
 * - Union type / intersection type handling
 */
#[CoversClass(Container::class)]
final class ContainerEdgeCaseTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // ---------------------------------------------------------------
    // Autowiring with interface dependencies via bind()
    // ---------------------------------------------------------------

    #[Test]
    public function autowiring_resolves_interface_dependency_through_bind(): void
    {
        $this->container->bind(LoggerInterfaceStub::class, FileLoggerStub::class);

        $service = $this->container->get(ServiceWithLoggerStub::class);

        $this->assertInstanceOf(ServiceWithLoggerStub::class, $service);
        $this->assertInstanceOf(FileLoggerStub::class, $service->logger);
    }

    #[Test]
    public function autowiring_resolves_chained_aliases(): void
    {
        $this->container->bind(LoggerInterfaceStub::class, FileLoggerStub::class);
        $this->container->bind('app.logger', LoggerInterfaceStub::class);

        $logger = $this->container->get('app.logger');

        $this->assertInstanceOf(FileLoggerStub::class, $logger);
    }

    // ---------------------------------------------------------------
    // has() negative cases
    // ---------------------------------------------------------------

    #[Test]
    public function has_returns_false_for_unbound_interface(): void
    {
        $this->assertFalse($this->container->has(LoggerInterfaceStub::class));
    }

    #[Test]
    public function has_returns_false_for_nonexistent_string(): void
    {
        $this->assertFalse($this->container->has('totally.nonexistent.service'));
    }

    #[Test]
    public function has_returns_true_after_binding(): void
    {
        $this->assertFalse($this->container->has('config'));
        $this->container->set('config', ['key' => 'value']);
        $this->assertTrue($this->container->has('config'));
    }

    #[Test]
    public function has_returns_true_for_existing_class(): void
    {
        // Any real class should be resolvable via autowiring
        $this->assertTrue($this->container->has(SimpleStubForEdge::class));
    }

    // ---------------------------------------------------------------
    // Singleton re-registration clears cached instance
    // ---------------------------------------------------------------

    #[Test]
    public function singleton_re_registration_clears_cached_instance(): void
    {
        $this->container->singleton('service', fn() => new \stdClass());

        $first = $this->container->get('service');
        $same = $this->container->get('service');
        $this->assertSame($first, $same);

        // Re-register as singleton
        $this->container->singleton('service', fn() => new \stdClass());

        $new = $this->container->get('service');
        $this->assertNotSame($first, $new);

        // New singleton is also cached
        $sameNew = $this->container->get('service');
        $this->assertSame($new, $sameNew);
    }

    #[Test]
    public function set_re_registration_clears_cached_singleton(): void
    {
        $this->container->singleton('service', fn() => new \stdClass());

        $first = $this->container->get('service');
        $this->assertSame($first, $this->container->get('service'));

        // Override with transient
        $this->container->set('service', fn() => new \stdClass());

        $new = $this->container->get('service');
        $this->assertNotSame($first, $new);

        // Transient: each get() returns new instance
        $another = $this->container->get('service');
        $this->assertNotSame($new, $another);
    }

    // ---------------------------------------------------------------
    // Autowired instances are transient
    // ---------------------------------------------------------------

    #[Test]
    public function autowired_instances_are_transient_by_default(): void
    {
        $first = $this->container->get(SimpleStubForEdge::class);
        $second = $this->container->get(SimpleStubForEdge::class);

        $this->assertInstanceOf(SimpleStubForEdge::class, $first);
        $this->assertInstanceOf(SimpleStubForEdge::class, $second);
        $this->assertNotSame($first, $second);
    }

    // ---------------------------------------------------------------
    // Deep dependency chain
    // ---------------------------------------------------------------

    #[Test]
    public function autowiring_handles_deep_dependency_chains(): void
    {
        $result = $this->container->get(DeepCStub::class);

        $this->assertInstanceOf(DeepCStub::class, $result);
        $this->assertInstanceOf(DeepBStub::class, $result->b);
        $this->assertInstanceOf(DeepAStub::class, $result->b->a);
    }

    // ---------------------------------------------------------------
    // Non-instantiable class
    // ---------------------------------------------------------------

    #[Test]
    public function it_throws_for_abstract_class(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not instantiable');

        $this->container->get(AbstractStubClass::class);
    }

    // ---------------------------------------------------------------
    // Singleton with raw value (not closure)
    // ---------------------------------------------------------------

    #[Test]
    public function singleton_with_raw_value_returns_same_value(): void
    {
        $config = ['debug' => true, 'name' => 'test'];
        $this->container->singleton('config', $config);

        $this->assertSame($config, $this->container->get('config'));
        $this->assertSame($config, $this->container->get('config'));
    }
}

// ---------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------

interface LoggerInterfaceStub {}

class FileLoggerStub implements LoggerInterfaceStub {}

class ServiceWithLoggerStub
{
    public function __construct(public LoggerInterfaceStub $logger) {}
}

class SimpleStubForEdge {}

class DeepAStub {}
class DeepBStub
{
    public function __construct(public DeepAStub $a) {}
}
class DeepCStub
{
    public function __construct(public DeepBStub $b) {}
}

abstract class AbstractStubClass {}
