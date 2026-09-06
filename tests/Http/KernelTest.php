<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Http\Exception\ExceptionHandler;
use FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\Exception\InvalidHandlerException;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    private Router $router;
    private Container $container;
    private HandlerInvoker $invoker;
    private ExceptionHandlerInterface $exceptionHandler;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->container = new Container();
        $this->invoker = new HandlerInvoker($this->container);
        $this->exceptionHandler = new ExceptionHandler();
    }

    #[Test]
    public function it_returns_404_when_route_is_not_found(): void
    {
        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/unknown');

        $response = $kernel->handle($request);

        $this->assertSame(404, $response->status());
        $this->assertSame('Not Found', $response->body());
    }

    #[Test]
    public function it_returns_405_when_method_is_not_allowed(): void
    {
        $this->router->get('/users', fn() => new Response());
        $this->router->post('/users', fn() => new Response());

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('DELETE', '/users');

        $response = $kernel->handle($request);

        $this->assertSame(405, $response->status());
        $this->assertSame('Method Not Allowed', $response->body());
        $this->assertSame('GET, POST', $response->header('Allow'));
    }

    #[Test]
    public function it_executes_static_route_handler(): void
    {
        $this->router->get('/health', function (Request $request, array $params): Response {
            return new Response('OK');
        });

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/health');

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('OK', $response->body());
    }

    #[Test]
    public function it_executes_dynamic_route_handler_and_passes_parameters(): void
    {
        $this->router->get('/users/{id}/posts/{postId}', function (Request $request, array $params): Response {
            $body = sprintf('User %s, Post %s', $params['id'], $params['postId']);
            return new Response($body);
        });

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/users/42/posts/99');

        $response = $kernel->handle($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('User 42, Post 99', $response->body());
    }

    #[Test]
    public function it_throws_if_handler_is_not_callable(): void
    {
        $this->router->get('/invalid', 'NotACallable@index');

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/invalid');

        // Note: HandlerInvoker resolves callable before route matches, inside invoke().
        // Wait! In v0.26, the exception handler catches this.
        // So it should return a 500 response, not bubble up!

        $response = $kernel->handle($request);

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
    }

    #[Test]
    public function exceptions_thrown_by_handler_are_caught_by_exception_handler(): void
    {
        $this->router->get('/boom', function () {
            throw new RuntimeException('Handler went boom');
        });

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/boom');

        $response = $kernel->handle($request);

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
    }

    #[Test]
    public function http_exceptions_thrown_by_handler_produce_controlled_responses(): void
    {
        $this->router->get('/boom', function () {
            throw new \FlintPHP\Framework\Http\Exception\HttpException(401, 'Unauthorized access');
        });

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/boom');

        $response = $kernel->handle($request);

        $this->assertSame(401, $response->status());
        $this->assertSame("Unauthorized access\n", $response->body());
    }

    #[Test]
    public function middleware_executes_before_and_after_routing(): void
    {
        $log = [];

        $middleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'Middleware before';
                $response = $next($request);
                $this->log[] = 'Middleware after';
                return $response;
            }
        };

        $this->router->get('/test', function () use (&$log): Response {
            $log[] = 'Handler executed';
            return new Response();
        });

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/test');

        $kernel->handle($request);

        $expectedLog = [
            'Middleware before',
            'Handler executed',
            'Middleware after',
        ];

        $this->assertSame($expectedLog, $log);
    }

    #[Test]
    public function middleware_short_circuit_prevents_routing(): void
    {
        $log = [];

        $middleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'Middleware short-circuited';
                return new Response('Blocked', 403);
            }
        };

        $this->router->get('/test', function () use (&$log): Response {
            $log[] = 'Handler executed'; // Should not happen
            return new Response();
        });

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/test');

        $response = $kernel->handle($request);

        $expectedLog = [
            'Middleware short-circuited',
        ];

        $this->assertSame($expectedLog, $log);
        $this->assertSame(403, $response->status());
        $this->assertSame('Blocked', $response->body());
    }

    #[Test]
    public function middleware_can_inspect_and_modify_404_responses(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response {
                $response = $next($request);

                if ($response->status() === 404) {
                    return $response->withHeader('X-Intercepted-404', 'true');
                }

                return $response;
            }
        };

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/unknown');

        $response = $kernel->handle($request);

        $this->assertSame(404, $response->status());
        $this->assertSame('true', $response->header('X-Intercepted-404'));
    }

    #[Test]
    public function exceptions_thrown_by_middleware_are_caught_by_exception_handler(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response {
                throw new RuntimeException('Middleware went boom');
            }
        };

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/test');

        $response = $kernel->handle($request);

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
    }

    #[Test]
    public function http_exceptions_thrown_by_middleware_produce_controlled_responses(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response {
                throw new \FlintPHP\Framework\Http\Exception\HttpException(403, 'Forbidden action');
            }
        };

        $kernel = new Kernel($this->router, new MiddlewareStack([$middleware]), $this->invoker, $this->exceptionHandler);
        $request = new Request('GET', '/test');

        $response = $kernel->handle($request);

        $this->assertSame(403, $response->status());
        $this->assertSame("Forbidden action\n", $response->body());
    }

    #[Test]
    public function exception_handler_receives_exact_request_and_throwable(): void
    {
        $thrownException = new RuntimeException('Handler failed');
        $originalRequest = new Request('GET', '/boom');

        $this->router->get('/boom', function () use ($thrownException) {
            throw $thrownException;
        });

        $capturedException = null;
        $capturedRequest = null;

        $testHandler = new class($capturedException, $capturedRequest) implements ExceptionHandlerInterface {
            public function __construct(public ?Throwable &$capturedEx, public ?Request &$capturedReq) {}
            public function handle(Throwable $exception, Request $request): Response {
                $this->capturedEx = $exception;
                $this->capturedReq = $request;
                return new Response('Handled');
            }
        };

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $testHandler);

        $kernel->handle($originalRequest);

        $this->assertSame($thrownException, $capturedException);
        $this->assertSame($originalRequest, $capturedRequest);
    }

    #[Test]
    public function exception_handler_failure_propagates_without_recursion(): void
    {
        $this->router->get('/boom', function () {
            throw new RuntimeException('Handler failed');
        });

        $failingHandler = new class() implements ExceptionHandlerInterface {
            public function handle(Throwable $exception, Request $request): Response {
                throw new \LogicException('Handler itself failed');
            }
        };

        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker, $failingHandler);
        $request = new Request('GET', '/boom');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Handler itself failed');

        $kernel->handle($request);
    }

    #[Test]
    public function kernel_can_be_constructed_with_three_arguments_and_handles_exceptions_securely(): void
    {
        $this->router->get('/boom', function () {
            throw new RuntimeException('Secret handler exception');
        });

        // 3 arguments, no ExceptionHandler provided
        $kernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker);
        $request = new Request('GET', '/boom');

        $response = $kernel->handle($request);

        $this->assertSame(500, $response->status());
        $this->assertSame("Internal Server Error\n", $response->body());
        $this->assertStringNotContainsString('Secret', $response->body());
    }

    #[Test]
    public function application_created_kernel_and_manually_created_kernel_behave_the_same(): void
    {
        $app = new \FlintPHP\Framework\Foundation\Application('/tmp');
        $app->router()->get('/fail', fn() => throw new RuntimeException('App boom'));

        $appKernel = $app->kernel();
        $appResponse = $appKernel->handle(new Request('GET', '/fail'));

        $this->router->get('/fail', fn() => throw new RuntimeException('Manual boom'));
        $manualKernel = new Kernel($this->router, new MiddlewareStack(), $this->invoker);
        $manualResponse = $manualKernel->handle(new Request('GET', '/fail'));

        $this->assertSame($appResponse->status(), $manualResponse->status());
        $this->assertSame($appResponse->body(), $manualResponse->body());
    }
}
