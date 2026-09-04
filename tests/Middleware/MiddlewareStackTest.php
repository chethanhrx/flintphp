<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Middleware;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MiddlewareStack::class)]
final class MiddlewareStackTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request('GET', '/');
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    #[Test]
    public function it_rejects_invalid_middleware(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement FlintPHP\Framework\Middleware\MiddlewareInterface. Got: string');

        new MiddlewareStack(['invalid string']);
    }

    #[Test]
    public function it_rejects_objects_that_do_not_implement_interface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Got: stdClass');

        new MiddlewareStack([new \stdClass()]);
    }

    #[Test]
    public function internal_array_cannot_be_mutated_externally(): void
    {
        $middlewares = [$this->createPassThroughMiddleware()];
        $stack = new MiddlewareStack($middlewares);

        // Mutate original array
        $middlewares[0] = 'invalid';
        $middlewares[] = $this->createPassThroughMiddleware();

        // Should still execute exactly one valid middleware
        $executed = false;
        $stack->handle($this->request, function (Request $req) use (&$executed): Response {
            $executed = true;
            return new Response();
        });

        $this->assertTrue($executed);
    }

    // ---------------------------------------------------------------
    // Execution
    // ---------------------------------------------------------------

    #[Test]
    public function empty_stack_executes_handler_directly(): void
    {
        $stack = new MiddlewareStack();

        $executed = false;
        $response = new Response('handler response');

        $actualResponse = $stack->handle($this->request, function (Request $req) use (&$executed, $response): Response {
            $executed = true;
            return $response;
        });

        $this->assertTrue($executed);
        $this->assertSame($response, $actualResponse);
    }

    #[Test]
    public function execution_order_is_correct_onion_model(): void
    {
        $log = [];

        $middlewareA = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'A before';
                $response = $next($request);
                $this->log[] = 'A after';
                return $response;
            }
        };

        $middlewareB = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'B before';
                $response = $next($request);
                $this->log[] = 'B after';
                return $response;
            }
        };

        $stack = new MiddlewareStack([$middlewareA, $middlewareB]);

        $stack->handle($this->request, function (Request $req) use (&$log): Response {
            $log[] = 'Handler';
            return new Response();
        });

        $expectedLog = [
            'A before',
            'B before',
            'Handler',
            'B after',
            'A after',
        ];

        $this->assertSame($expectedLog, $log);
    }

    #[Test]
    public function short_circuiting_prevents_subsequent_execution(): void
    {
        $log = [];

        $middlewareA = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'A before';
                $response = clone $next($request); // simulate immutability
                $this->log[] = 'A after';
                return $response;
            }
        };

        $shortCircuit = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'ShortCircuit';
                return new Response('blocked', 403);
            }
        };

        $middlewareC = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response {
                $this->log[] = 'C before';
                $response = $next($request);
                $this->log[] = 'C after';
                return $response;
            }
        };

        $stack = new MiddlewareStack([$middlewareA, $shortCircuit, $middlewareC]);

        $response = $stack->handle($this->request, function (Request $req) use (&$log): Response {
            $log[] = 'Handler';
            return new Response();
        });

        $expectedLog = [
            'A before',
            'ShortCircuit',
            'A after',
        ];

        $this->assertSame($expectedLog, $log);
        $this->assertSame(403, $response->status());
        $this->assertSame('blocked', $response->body());
    }

    #[Test]
    public function exceptions_propagate_transparently(): void
    {
        $middleware = new class() implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response {
                return $next($request);
            }
        };

        $stack = new MiddlewareStack([$middleware]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Handler failed');

        $stack->handle($this->request, function (Request $req): Response {
            throw new RuntimeException('Handler failed');
        });
    }

    #[Test]
    public function handler_can_be_invokable_class(): void
    {
        $stack = new MiddlewareStack();
        $response = new Response('invokable');

        $handler = new class($response) {
            public function __construct(private Response $res) {}
            public function __invoke(Request $request): Response {
                return $this->res;
            }
        };

        $actual = $stack->handle($this->request, $handler);

        $this->assertSame($response, $actual);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createPassThroughMiddleware(): MiddlewareInterface
    {
        return new class() implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response {
                return $next($request);
            }
        };
    }
}
