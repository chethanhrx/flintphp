<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use Error;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Exception\ExceptionHandler;
use FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Router;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

/**
 * Edge-case tests for the Kernel exception boundary, middleware ordering,
 * and multi-request handling.
 *
 * These tests cover critical gaps identified in the Phase A audit:
 * - PHP Errors (not just Exceptions) are caught
 * - Global middleware wraps scoped middleware ("onion within onion")
 * - Exceptions thrown after $next() in middleware are caught
 * - Kernel handles multiple sequential requests without state leaking
 * - Invalid scoped middleware class is rejected with a clear error
 */
#[CoversClass(Kernel::class)]
final class KernelEdgeCaseTest extends TestCase
{
    private Router $router;
    private Container $container;
    private HandlerInvoker $invoker;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->container = new Container();
        $this->invoker = new HandlerInvoker($this->container);
    }

    // ---------------------------------------------------------------
    // PHP Error / TypeError boundary (Throwable, not just Exception)
    // ---------------------------------------------------------------

    #[Test]
    public function kernel_catches_php_type_error_in_handler(): void
    {
        $this->router->get('/type-error', function (): Response {
            // This will cause a TypeError at runtime
            /** @phpstan-ignore-next-line */
            strlen([]); // @phpcs:ignore
            return new Response(); // unreachable
        });

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker);
        $response = $kernel->handle(new Request('GET', '/type-error'));

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
    }

    #[Test]
    public function kernel_catches_php_error_in_handler(): void
    {
        $this->router->get('/error', function (): Response {
            throw new Error('Fatal error simulation');
        });

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker);
        $response = $kernel->handle(new Request('GET', '/error'));

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
        $this->assertStringNotContainsString('Fatal error simulation', $response->body());
    }

    #[Test]
    public function kernel_catches_throwable_in_middleware(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                throw new Error('Middleware fatal error');
            }
        };

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker);
        $response = $kernel->handle(new Request('GET', '/anything'));

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('fatal error', $response->body());
    }

    // ---------------------------------------------------------------
    // Exception thrown AFTER $next() in middleware
    // ---------------------------------------------------------------

    #[Test]
    public function exception_in_middleware_after_next_is_caught(): void
    {
        $log = [];

        $middleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'before';
                $response = $next($request);
                $this->log[] = 'after-before-throw';
                throw new RuntimeException('Post-next failure');
            }
        };

        $this->router->get('/test', function () use (&$log): Response {
            $log[] = 'handler';
            return new Response('OK');
        });

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker);
        $response = $kernel->handle(new Request('GET', '/test'));

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
        $this->assertSame(['before', 'handler', 'after-before-throw'], $log);
    }

    // ---------------------------------------------------------------
    // Global middleware wraps scoped middleware (onion within onion)
    // ---------------------------------------------------------------

    #[Test]
    public function global_middleware_wraps_scoped_middleware(): void
    {
        $log = [];

        $globalMiddleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'global-before';
                $response = $next($request);
                $this->log[] = 'global-after';
                return $response;
            }
        };

        $scopedMiddleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'scoped-before';
                $response = $next($request);
                $this->log[] = 'scoped-after';
                return $response;
            }
        };

        $scopedClass = get_class($scopedMiddleware);

        $this->router->get('/ordered', function () use (&$log): Response {
            $log[] = 'handler';
            return new Response('OK');
        }, middleware: [$scopedClass]);

        $this->container->singleton($scopedClass, $scopedMiddleware);

        $kernel = new Kernel(
            $this->router,
            new MiddlewareStack([$globalMiddleware]),
            $this->invoker,
            null,
            $this->container,
        );

        $response = $kernel->handle(new Request('GET', '/ordered'));

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'global-before',
            'scoped-before',
            'handler',
            'scoped-after',
            'global-after',
        ], $log);
    }

    #[Test]
    public function multiple_global_and_scoped_middleware_execute_in_correct_onion_order(): void
    {
        $log = [];

        $globalA = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'G-A-before';
                $response = $next($request);
                $this->log[] = 'G-A-after';
                return $response;
            }
        };

        $globalB = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'G-B-before';
                $response = $next($request);
                $this->log[] = 'G-B-after';
                return $response;
            }
        };

        $scopedX = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'S-X-before';
                $response = $next($request);
                $this->log[] = 'S-X-after';
                return $response;
            }
        };

        $scopedY = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'S-Y-before';
                $response = $next($request);
                $this->log[] = 'S-Y-after';
                return $response;
            }
        };

        $scopedXClass = get_class($scopedX);
        $scopedYClass = get_class($scopedY);

        $this->router->get('/multi', function () use (&$log): Response {
            $log[] = 'handler';
            return new Response('OK');
        }, middleware: [$scopedXClass, $scopedYClass]);

        $this->container->singleton($scopedXClass, $scopedX);
        $this->container->singleton($scopedYClass, $scopedY);

        $kernel = new Kernel(
            $this->router,
            new MiddlewareStack([$globalA, $globalB]),
            $this->invoker,
            null,
            $this->container,
        );

        $response = $kernel->handle(new Request('GET', '/multi'));

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'G-A-before',
            'G-B-before',
            'S-X-before',
            'S-Y-before',
            'handler',
            'S-Y-after',
            'S-X-after',
            'G-B-after',
            'G-A-after',
        ], $log);
    }

    // ---------------------------------------------------------------
    // Invalid scoped middleware
    // ---------------------------------------------------------------

    #[Test]
    public function kernel_rejects_scoped_middleware_that_does_not_implement_interface(): void
    {
        $notMiddleware = new \stdClass();
        $className = get_class($notMiddleware);

        $this->router->get('/bad', fn(): Response => new Response(), middleware: [$className]);
        $this->container->singleton($className, $notMiddleware);

        $kernel = new Kernel(
            $this->router,
            new MiddlewareStack(),
            $this->invoker,
            null,
            $this->container,
        );

        // The exception is caught by the exception handler since it's inside Kernel::handle()
        $response = $kernel->handle(new Request('GET', '/bad'));

        $this->assertSame(500, $response->status());
    }

    // ---------------------------------------------------------------
    // Sequential request handling (no state leak)
    // ---------------------------------------------------------------

    #[Test]
    public function kernel_handles_multiple_requests_without_state_leak(): void
    {
        $this->router->get('/a', fn(): Response => new Response('response-a'));
        $this->router->get('/b', fn(): Response => new Response('response-b'));
        $this->router->post('/a', fn(): Response => new Response('post-a', 201));

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker);

        $responseA = $kernel->handle(new Request('GET', '/a'));
        $responseB = $kernel->handle(new Request('GET', '/b'));
        $responsePost = $kernel->handle(new Request('POST', '/a'));
        $response404 = $kernel->handle(new Request('GET', '/nonexistent'));

        $this->assertSame(200, $responseA->status());
        $this->assertSame('response-a', $responseA->body());

        $this->assertSame(200, $responseB->status());
        $this->assertSame('response-b', $responseB->body());

        $this->assertSame(201, $responsePost->status());
        $this->assertSame('post-a', $responsePost->body());

        $this->assertSame(404, $response404->status());
    }

    #[Test]
    public function kernel_handles_error_then_success_sequentially(): void
    {
        $this->router->get('/boom', function (): Response {
            throw new RuntimeException('fail');
        });
        $this->router->get('/ok', fn(): Response => new Response('success'));

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker);

        $errorResponse = $kernel->handle(new Request('GET', '/boom'));
        $this->assertSame(500, $errorResponse->status());

        // Kernel must recover cleanly
        $okResponse = $kernel->handle(new Request('GET', '/ok'));
        $this->assertSame(200, $okResponse->status());
        $this->assertSame('success', $okResponse->body());
    }
}
