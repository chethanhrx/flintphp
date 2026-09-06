<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Foundation;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Contract\ConfigRepositoryInterface;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Foundation\FlintPHP;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    // ── Backward Compatibility & Paths ──

    #[Test]
    public function it_can_be_instantiated_with_a_base_path(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertInstanceOf(Application::class, $app);
    }

    #[Test]
    public function it_returns_the_base_path(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertSame('/var/www/myapp', $app->basePath());
    }

    #[Test]
    public function it_strips_trailing_separator_from_base_path(): void
    {
        $app = new Application('/var/www/myapp/');

        $this->assertSame('/var/www/myapp', $app->basePath());
    }

    #[Test]
    public function it_strips_multiple_trailing_separators_from_base_path(): void
    {
        $app = new Application('/var/www/myapp///');

        $this->assertSame('/var/www/myapp', $app->basePath());
    }

    #[Test]
    public function it_preserves_root_path(): void
    {
        $app = new Application('/');

        $this->assertSame('/', $app->basePath());
    }

    #[Test]
    public function it_returns_the_framework_version(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertSame(FlintPHP::VERSION, $app->version());
    }

    // ── Construction ──

    #[Test]
    public function existing_one_argument_constructor_remains_valid(): void
    {
        $app = new Application('/var/www/myapp');
        $this->assertInstanceOf(Application::class, $app);
    }

    #[Test]
    public function it_works_with_empty_configuration_repository(): void
    {
        // When not explicitly passed, a sensible empty config is used
        $app = new Application('/var/www/myapp');

        $this->assertInstanceOf(ConfigRepositoryInterface::class, $app->config());
        $this->assertEmpty($app->config()->all());
    }

    #[Test]
    public function explicit_configuration_works_and_repository_identity_is_preserved(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);

        $app = new Application('/var/www/myapp', $config);

        $this->assertSame($config, $app->config());
    }

    // ── Container ──

    #[Test]
    public function application_exposes_its_container(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertInstanceOf(Container::class, $app->container());
    }

    #[Test]
    public function container_is_instance_scoped(): void
    {
        $app1 = new Application('/var/www/myapp');
        $app2 = new Application('/var/www/myapp');

        $this->assertNotSame($app1->container(), $app2->container());
    }

    #[Test]
    public function configured_repository_is_registered_and_resolves_to_exact_instance(): void
    {
        $config = new ConfigRepository(['env' => 'testing']);
        $app = new Application('/var/www/myapp', $config);

        $resolved = $app->container()->get(ConfigRepositoryInterface::class);

        $this->assertSame($config, $resolved);
    }

    // ── Configuration ──

    #[Test]
    public function configuration_returns_expected_data(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'FlintPHP']]);
        $app = new Application('/var/www/myapp', $config);

        $this->assertSame('FlintPHP', $app->config()->get('app.name'));
    }

    // ── Boot ──

    #[Test]
    public function it_is_not_booted_before_boot_is_called(): void
    {
        $app = new Application('/var/www/myapp');

        $this->assertFalse($app->isBooted());
    }

    #[Test]
    public function it_is_booted_after_boot_is_called(): void
    {
        $app = new Application('/var/www/myapp');
        $app->boot();

        $this->assertTrue($app->isBooted());
    }

    #[Test]
    public function repeated_boot_is_idempotent(): void
    {
        $config = new ConfigRepository([]);
        $app = new Application('/var/www/myapp', $config);

        $app->boot();

        $containerBefore = $app->container();
        $configBefore = $app->config();

        $app->boot();
        $app->boot();

        $this->assertTrue($app->isBooted());
        // does not replace container
        $this->assertSame($containerBefore, $app->container());
        // does not replace config
        $this->assertSame($configBefore, $app->config());
        // does not duplicate registration (resolves to same instance)
        $this->assertSame($config, $app->container()->get(ConfigRepositoryInterface::class));
    }

    // ── Isolation ──

    #[Test]
    public function applications_are_completely_isolated(): void
    {
        $appA = new Application('/var/www/appA', new ConfigRepository(['name' => 'App A']));
        $appB = new Application('/var/www/appB', new ConfigRepository(['name' => 'App B']));

        // different containers
        $this->assertNotSame($appA->container(), $appB->container());

        // different configuration repositories
        $this->assertNotSame($appA->config(), $appB->config());

        // no state leakage
        $this->assertSame('App A', $appA->config()->get('name'));
        $this->assertSame('App B', $appB->config()->get('name'));

        // booting A does not affect B
        $appA->boot();
        $this->assertTrue($appA->isBooted());
        $this->assertFalse($appB->isBooted());

        // booting B does not affect A
        $appB->boot();
        $this->assertTrue($appB->isBooted());
        $this->assertTrue($appA->isBooted());
    }
    // ── HTTP Runtime Composition ──

    #[Test]
    public function application_owns_and_exposes_the_router(): void
    {
        $app = new Application('/var/www/myapp');
        $router = $app->router();

        $this->assertInstanceOf(\FlintPHP\Framework\Routing\Router::class, $router);
        $this->assertSame($router, $app->router()); // identity preserved
    }

    #[Test]
    public function application_owns_and_exposes_the_middleware_stack(): void
    {
        $app = new Application('/var/www/myapp');
        $middleware = $app->middleware();

        $this->assertInstanceOf(\FlintPHP\Framework\Middleware\MiddlewareStack::class, $middleware);
        $this->assertSame($middleware, $app->middleware()); // identity preserved
    }

    #[Test]
    public function application_owns_and_exposes_the_kernel(): void
    {
        $app = new Application('/var/www/myapp');
        $kernel = $app->kernel();

        $this->assertInstanceOf(\FlintPHP\Framework\Http\Kernel::class, $kernel);
        $this->assertSame($kernel, $app->kernel()); // identity preserved
    }

    #[Test]
    public function application_owns_and_exposes_the_exception_handler(): void
    {
        $app = new Application('/var/www/myapp');
        $exceptionHandler = $app->exceptionHandler();

        $this->assertInstanceOf(\FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface::class, $exceptionHandler);
        $this->assertSame($exceptionHandler, $app->exceptionHandler()); // identity preserved
    }

    #[Test]
    public function runtime_components_are_registered_in_the_container(): void
    {
        $app = new Application('/var/www/myapp');
        $container = $app->container();

        // Exact identity tests
        $this->assertSame($app->router(), $container->get(\FlintPHP\Framework\Routing\Router::class));
        $this->assertSame($app->middleware(), $container->get(\FlintPHP\Framework\Middleware\MiddlewareStack::class));
        $this->assertSame($app->kernel(), $container->get(\FlintPHP\Framework\Http\Kernel::class));
        $this->assertSame($app->exceptionHandler(), $container->get(\FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface::class));

        // HandlerInvoker is also registered
        $this->assertInstanceOf(\FlintPHP\Framework\Routing\HandlerInvoker::class, $container->get(\FlintPHP\Framework\Routing\HandlerInvoker::class));
    }

    #[Test]
    public function kernel_uses_the_exact_application_owned_router_and_middleware_stack_and_exception_handler(): void
    {
        $app = new Application('/var/www/myapp');

        $kernel = $app->kernel();

        $reflection = new \ReflectionClass($kernel);

        $routerProperty = $reflection->getProperty('router');
        $routerProperty->setAccessible(true);
        $kernelRouter = $routerProperty->getValue($kernel);

        $middlewareProperty = $reflection->getProperty('middlewareStack');
        $middlewareProperty->setAccessible(true);
        $kernelMiddleware = $middlewareProperty->getValue($kernel);

        $exceptionHandlerProperty = $reflection->getProperty('exceptionHandler');
        $exceptionHandlerProperty->setAccessible(true);
        $kernelExceptionHandler = $exceptionHandlerProperty->getValue($kernel);

        $this->assertSame($app->router(), $kernelRouter, 'Kernel must use the exact Application-owned Router instance.');
        $this->assertSame($app->middleware(), $kernelMiddleware, 'Kernel must use the exact Application-owned MiddlewareStack instance.');
        $this->assertSame($app->exceptionHandler(), $kernelExceptionHandler, 'Kernel must use the exact Application-owned ExceptionHandler instance.');
    }

    #[Test]
    public function kernel_uses_the_application_router(): void
    {
        $app = new Application('/var/www/myapp');

        $app->router()->get('/hello', function (\FlintPHP\Framework\Http\Request $req) {
            return new \FlintPHP\Framework\Http\Response('Hello World');
        });

        $request = new \FlintPHP\Framework\Http\Request('GET', '/hello');
        $response = $app->kernel()->handle($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('Hello World', $response->body());
    }



    #[Test]
    public function runtime_components_are_completely_isolated(): void
    {
        $appA = new Application('/var/www/appA');
        $appB = new Application('/var/www/appB');

        $this->assertNotSame($appA->router(), $appB->router());
        $this->assertNotSame($appA->middleware(), $appB->middleware());
        $this->assertNotSame($appA->kernel(), $appB->kernel());
        $this->assertNotSame($appA->exceptionHandler(), $appB->exceptionHandler());

        // Routes in appA do not leak to appB
        $appA->router()->get('/only-a', function () { return new \FlintPHP\Framework\Http\Response('A'); });

        $req = new \FlintPHP\Framework\Http\Request('GET', '/only-a');

        $this->assertSame(200, $appA->kernel()->handle($req)->status());
        $this->assertSame(404, $appB->kernel()->handle($req)->status());
    }

    // ── Bootstrappers (v0.28) ──

    #[Test]
    public function bootstrapWith_does_nothing_with_empty_array(): void
    {
        $app = new Application('/var/www/myapp');

        $throwingBootstrapper = new class() implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function bootstrap(Application $app): void {
                throw new \RuntimeException('Should not be executed');
            }
        };

        $app->bootstrapWith([]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function bootstrapWith_executes_one_bootstrapper(): void
    {
        $app = new Application('/var/www/myapp');
        $executed = false;

        $bootstrapper = new class($executed) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private bool &$executed) {}
            public function bootstrap(Application $app): void { $this->executed = true; }
        };

        $app->bootstrapWith([$bootstrapper]);

        $this->assertTrue($executed);
    }

    #[Test]
    public function bootstrapWith_executes_multiple_bootstrappers_in_exact_order(): void
    {
        $app = new Application('/var/www/myapp');
        $log = [];

        $createBootstrapper = function (string $name) use (&$log) {
            return new class($name, $log) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
                public function __construct(private string $name, private array &$log) {}
                public function bootstrap(Application $app): void { $this->log[] = $this->name; }
            };
        };

        $app->bootstrapWith([
            $createBootstrapper('first'),
            $createBootstrapper('second'),
            $createBootstrapper('third'),
        ]);

        $this->assertSame(['first', 'second', 'third'], $log);
    }

    #[Test]
    public function bootstrapWith_passes_exact_application_identity(): void
    {
        $app = new Application('/var/www/myapp');

        $bootstrapper = new class() implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public ?Application $receivedApp = null;
            public function bootstrap(Application $app): void { $this->receivedApp = $app; }
        };

        $app->bootstrapWith([$bootstrapper]);

        $this->assertSame($app, $bootstrapper->receivedApp);
    }

    #[Test]
    public function bootstrapper_can_configure_existing_container(): void
    {
        $app = new Application('/var/www/myapp');

        $bootstrapper = new class() implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function bootstrap(Application $app): void {
                $app->container()->singleton('test_service', new \stdClass());
            }
        };

        $app->bootstrapWith([$bootstrapper]);

        $this->assertTrue($app->container()->has('test_service'));
        $this->assertInstanceOf(\stdClass::class, $app->container()->get('test_service'));
    }

    #[Test]
    public function bootstrapper_can_access_existing_configuration(): void
    {
        $config = new ConfigRepository(['foo' => 'bar']);
        $app = new Application('/var/www/myapp', $config);
        $readConfigValue = null;

        $bootstrapper = new class($readConfigValue) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private &$readConfigValue) {}
            public function bootstrap(Application $app): void {
                $this->readConfigValue = $app->config()->get('foo');
            }
        };

        $app->bootstrapWith([$bootstrapper]);

        $this->assertSame('bar', $readConfigValue);
    }

    #[Test]
    public function bootstrapper_can_configure_existing_router(): void
    {
        $app = new Application('/var/www/myapp');

        $bootstrapper = new class() implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function bootstrap(Application $app): void {
                $app->router()->get('/bootstrapper-route', function () {
                    return new \FlintPHP\Framework\Http\Response('Route added');
                });
            }
        };

        $app->bootstrapWith([$bootstrapper]);

        $request = new \FlintPHP\Framework\Http\Request('GET', '/bootstrapper-route');
        $response = $app->kernel()->handle($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('Route added', $response->body());
    }

    #[Test]
    public function bootstrapper_exceptions_propagate_unchanged(): void
    {
        $app = new Application('/var/www/myapp');
        $exception = new \RuntimeException('Bootstrapper failed');

        $bootstrapper = new class($exception) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private \Throwable $exception) {}
            public function bootstrap(Application $app): void { throw $this->exception; }
        };

        try {
            $app->bootstrapWith([$bootstrapper]);
            $this->fail('Exception was not thrown');
        } catch (\Throwable $e) {
            $this->assertSame($exception, $e);
        }
    }

    #[Test]
    public function later_bootstrappers_do_not_execute_after_failure(): void
    {
        $app = new Application('/var/www/myapp');
        $executed = false;

        $failing = new class() implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function bootstrap(Application $app): void { throw new \RuntimeException('Fail'); }
        };

        $later = new class($executed) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private bool &$executed) {}
            public function bootstrap(Application $app): void { $this->executed = true; }
        };

        try {
            $app->bootstrapWith([$failing, $later]);
        } catch (\RuntimeException $e) {
            // caught
        }

        $this->assertFalse($executed);
    }

    #[Test]
    public function repeated_bootstrapWith_calls_execute_again(): void
    {
        $app = new Application('/var/www/myapp');
        $count = 0;

        $bootstrapper = new class($count) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private int &$count) {}
            public function bootstrap(Application $app): void { $this->count++; }
        };

        $app->bootstrapWith([$bootstrapper]);
        $app->bootstrapWith([$bootstrapper]);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function duplicate_bootstrapper_instances_execute_twice(): void
    {
        $app = new Application('/var/www/myapp');
        $count = 0;

        $bootstrapper = new class($count) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private int &$count) {}
            public function bootstrap(Application $app): void { $this->count++; }
        };

        $app->bootstrapWith([$bootstrapper, $bootstrapper]);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function invalid_array_element_is_rejected(): void
    {
        $app = new Application('/var/www/myapp');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an array of FlintPHP\Framework\Foundation\BootstrapperInterface instances');

        $app->bootstrapWith([new \stdClass()]);
    }

    #[Test]
    public function invalid_value_stops_processing(): void
    {
        $app = new Application('/var/www/myapp');
        $executed = false;

        $later = new class($executed) implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function __construct(private bool &$executed) {}
            public function bootstrap(Application $app): void { $this->executed = true; }
        };

        try {
            $app->bootstrapWith(['invalid_string', $later]);
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertFalse($executed);
    }

    #[Test]
    public function caller_array_is_not_mutated(): void
    {
        $app = new Application('/var/www/myapp');

        $bootstrapper = new class() implements \FlintPHP\Framework\Foundation\BootstrapperInterface {
            public function bootstrap(Application $app): void {}
        };

        $array = [$bootstrapper];
        $original = $array;

        $app->bootstrapWith($array);

        $this->assertSame($original, $array);
    }

    // ── Middleware Composition ──

    #[Test]
    public function application_starts_with_empty_middleware_stack(): void
    {
        $app = new Application('/var/www/myapp');
        $app->router()->get('/', function () { return new \FlintPHP\Framework\Http\Response('OK'); });

        $response = $app->kernel()->handle(new \FlintPHP\Framework\Http\Request('GET', '/'));
        $this->assertSame('OK', $response->body());
    }

    #[Test]
    public function addMiddleware_accepts_class_string_and_resolves_through_container(): void
    {
        $app = new Application('/var/www/myapp');
        $executed = false;

        $middleware = new class($executed) implements \FlintPHP\Framework\Middleware\MiddlewareInterface {
            public function __construct(public bool &$executed) {}
            public function process(\FlintPHP\Framework\Http\Request $req, callable $next): \FlintPHP\Framework\Http\Response {
                $this->executed = true;
                return $next($req);
            }
        };

        $app->container()->singleton('MyTestMiddleware', $middleware);
        $app->addMiddleware('MyTestMiddleware');

        $app->router()->get('/', function () { return new \FlintPHP\Framework\Http\Response('OK'); });
        $app->kernel()->handle(new \FlintPHP\Framework\Http\Request('GET', '/'));

        $this->assertTrue($executed);
    }

    #[Test]
    public function missing_middleware_produces_container_not_found_exception(): void
    {
        $app = new Application('/var/www/myapp');
        $app->addMiddleware('NonExistentMiddleware');

        $this->expectException(\FlintPHP\Framework\Container\NotFoundException::class);
        $app->middleware();
    }

    #[Test]
    public function resolvable_non_middleware_class_produces_invalid_argument_exception(): void
    {
        $app = new Application('/var/www/myapp');
        $app->container()->singleton('NotAMiddleware', new \stdClass());
        $app->addMiddleware('NotAMiddleware');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement FlintPHP\Framework\Middleware\MiddlewareInterface');

        $app->middleware();
    }

    #[Test]
    public function duplicate_middleware_registrations_are_preserved_and_order_is_exact(): void
    {
        $app = new Application('/var/www/myapp');
        $log = [];

        $createMiddleware = function(string $name) use (&$log) {
            return new class($name, $log) implements \FlintPHP\Framework\Middleware\MiddlewareInterface {
                public function __construct(private string $name, private array &$log) {}
                public function process(\FlintPHP\Framework\Http\Request $req, callable $next): \FlintPHP\Framework\Http\Response {
                    $this->log[] = $this->name;
                    return $next($req);
                }
            };
        };

        $app->container()->singleton('A', $createMiddleware('A'));
        $app->container()->singleton('B', $createMiddleware('B'));

        $app->addMiddleware('A');
        $app->addMiddleware('B');
        $app->addMiddleware('A'); // duplicate

        $app->router()->get('/', function () { return new \FlintPHP\Framework\Http\Response('OK'); });
        $app->kernel()->handle(new \FlintPHP\Framework\Http\Request('GET', '/'));

        $this->assertSame(['A', 'B', 'A'], $log);
    }

    #[Test]
    public function middleware_pipeline_freezes_and_throws_on_late_registration_via_middleware(): void
    {
        $app = new Application('/var/www/myapp');
        $app->middleware(); // Freezes pipeline

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add middleware after the HTTP pipeline has been finalized.');

        $app->addMiddleware('SomeMiddleware');
    }

    #[Test]
    public function middleware_pipeline_freezes_and_throws_on_late_registration_via_kernel(): void
    {
        $app = new Application('/var/www/myapp');
        $app->kernel(); // Also triggers middleware() internally

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add middleware after the HTTP pipeline has been finalized.');

        $app->addMiddleware('SomeMiddleware');
    }

    #[Test]
    public function middleware_pipeline_freezes_and_throws_on_late_registration_via_container(): void
    {
        $app = new Application('/var/www/myapp');
        $app->container()->get(\FlintPHP\Framework\Middleware\MiddlewareStack::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add middleware after the HTTP pipeline has been finalized.');

        $app->addMiddleware('SomeMiddleware');
    }

    #[Test]
    public function container_identity_guarantees_for_middleware_stack(): void
    {
        $app = new Application('/var/www/myapp');

        $direct = $app->middleware();
        $fromContainer = $app->container()->get(\FlintPHP\Framework\Middleware\MiddlewareStack::class);

        $this->assertSame($direct, $fromContainer);
    }

    #[Test]
    public function container_identity_guarantees_for_kernel(): void
    {
        $app = new Application('/var/www/myapp');

        $direct = $app->kernel();
        $fromContainer = $app->container()->get(\FlintPHP\Framework\Http\Kernel::class);

        $this->assertSame($direct, $fromContainer);
    }

    #[Test]
    public function kernel_uses_exactly_the_same_middleware_stack(): void
    {
        $app = new Application('/var/www/myapp');

        $kernel = $app->kernel();
        $middleware = $app->middleware();

        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('middlewareStack');
        $property->setAccessible(true);
        $kernelMiddleware = $property->getValue($kernel);

        $this->assertSame($middleware, $kernelMiddleware);
    }
}
