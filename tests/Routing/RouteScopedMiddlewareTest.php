<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Routing;

use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\RoutingException;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Route;
use FlintPHP\Framework\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Route::class)]
#[CoversClass(Router::class)]
final class RouteScopedMiddlewareTest extends TestCase
{
    #[Test]
    public function route_defaults_to_empty_middleware(): void
    {
        $route = new Route('GET', '/users', fn (): Response => new Response());

        $this->assertSame([], $route->middleware());
    }

    #[Test]
    public function route_accepts_and_normalizes_middleware_list(): void
    {
        $route = new Route('GET', '/users', fn (): Response => new Response(), null, ['AuthMiddleware']);

        $this->assertSame(['AuthMiddleware'], $route->middleware());
    }

    #[Test]
    public function route_rejects_non_string_middleware_entries(): void
    {
        $this->expectException(RoutingException::class);

        new Route('GET', '/users', fn (): Response => new Response(), null, [42]);
    }

    #[Test]
    public function route_rejects_empty_string_middleware_entries(): void
    {
        $this->expectException(RoutingException::class);

        new Route('GET', '/users', fn (): Response => new Response(), null, ['']);
    }

    #[Test]
    public function withMiddleware_returns_new_instance_preserving_original(): void
    {
        $handler = fn (): Response => new Response();
        $route = new Route('GET', '/users/{id}', $handler, 'users.show', ['A']);

        $derived = $route->withMiddleware(['B', 'C']);

        $this->assertNotSame($route, $derived);
        $this->assertSame(['A'], $route->middleware());
        $this->assertSame(['A', 'B', 'C'], $derived->middleware());
        $this->assertSame($handler, $derived->handler());
        $this->assertSame('users.show', $derived->name());
        $this->assertSame('/users/{id}', $derived->pattern());
        $this->assertSame('GET', $derived->method());
    }

    #[Test]
    public function router_verb_methods_accept_optional_middleware(): void
    {
        $router = new Router();

        $router->get('/a', fn (): Response => new Response(), null, ['M1']);
        $router->post('/b', fn (): Response => new Response(), null, ['M2']);
        $router->put('/c', fn (): Response => new Response(), null, ['M3']);
        $router->patch('/d', fn (): Response => new Response(), null, ['M4']);
        $router->delete('/e', fn (): Response => new Response(), null, ['M5']);
        $router->head('/f', fn (): Response => new Response(), null, ['M6']);
        $router->options('/g', fn (): Response => new Response(), null, ['M7']);
        $router->add('GET', '/h', fn (): Response => new Response(), null, ['M8']);

        $expected = [
            '/a' => ['M1'],
            '/b' => ['M2'],
            '/c' => ['M3'],
            '/d' => ['M4'],
            '/e' => ['M5'],
            '/f' => ['M6'],
            '/g' => ['M7'],
            '/h' => ['M8'],
        ];

        foreach ($router->routes() as $route) {
            $this->assertSame($expected[$route->pattern()], $route->middleware());
        }
    }

    #[Test]
    public function routes_without_middleware_keep_working(): void
    {
        $router = new Router();
        $router->get('/plain', fn (): Response => new Response('plain'));

        $route = $router->match(new Request('GET', '/plain'))->route();

        $this->assertNotNull($route);
        $this->assertSame([], $route->middleware());
    }

    #[Test]
    public function kernel_executes_scoped_middleware_around_handler(): void
    {
        $log = [];

        $middleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}

            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'scoped-before';
                $response = $next($request);
                $this->log[] = 'scoped-after';

                return $response;
            }
        };

        $router = new Router();
        $router->get('/scoped', function () use (&$log): Response {
            $log[] = 'handler';

            return new Response('ok');
        }, middleware: [get_class($middleware)]);

        $container = new Container();
        $container->singleton(get_class($middleware), $middleware);

        $kernel = new Kernel(
            $router,
            new MiddlewareStack(),
            new HandlerInvoker($container),
            null,
            $container
        );

        $response = $kernel->handle(new Request('GET', '/scoped'));

        $this->assertSame(200, $response->status());
        $this->assertSame(['scoped-before', 'handler', 'scoped-after'], $log);
    }

    #[Test]
    public function kernel_resolves_scoped_middleware_through_container(): void
    {
        $spy = new class implements MiddlewareInterface {
            public int $executed = 0;

            public function process(Request $request, callable $next): Response
            {
                $this->executed++;

                return $next($request);
            }
        };

        $router = new Router();
        $router->get('/inject', fn (): Response => new Response('ok'), middleware: [get_class($spy)]);

        $container = new Container();
        $container->singleton(get_class($spy), $spy);

        $kernel = new Kernel(
            $router,
            new MiddlewareStack(),
            new HandlerInvoker($container),
            null,
            $container
        );

        $kernel->handle(new Request('GET', '/inject'));

        $this->assertSame(1, $spy->executed);
    }

    #[Test]
    public function scoped_middleware_can_short_circuit(): void
    {
        $router = new Router();
        $router->get('/blocked', function (): void {
            throw new RuntimeException('handler must never run');
        }, middleware: [BlockingMiddleware::class]);

        $container = new Container();
        $kernel = new Kernel(
            $router,
            new MiddlewareStack(),
            new HandlerInvoker($container),
            null,
            $container
        );

        $response = $kernel->handle(new Request('GET', '/blocked'));

        $this->assertSame(418, $response->status());
        $this->assertSame('blocked', $response->body());
    }

    #[Test]
    public function scoped_middleware_exceptions_hit_the_exception_handler(): void
    {
        $router = new Router();
        $router->get('/boom', fn (): Response => new Response(), middleware: [ExplodingMiddleware::class]);

        $container = new Container();
        $kernel = new Kernel(
            $router,
            new MiddlewareStack(),
            new HandlerInvoker($container),
            null,
            $container
        );

        $response = $kernel->handle(new Request('GET', '/boom'));

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('scoped secret', $response->body());
    }

    #[Test]
    public function scoped_middleware_composes_with_dynamic_route_parameters(): void
    {
        $router = new Router();
        $router->get('/users/{id}', function (Request $request, int $id): Response {
            return new Response(sprintf(
                'user:%d|marker:%s',
                $id,
                $request->getAttribute('marker') ?? 'missing'
            ));
        }, middleware: [AttributeCapturingMiddleware::class]);

        $container = new Container();
        $kernel = new Kernel(
            $router,
            new MiddlewareStack(),
            new HandlerInvoker($container),
            null,
            $container
        );

        $response = $kernel->handle(new Request('GET', '/users/7'));

        // Typed route parameters still resolve, and attributes set by scoped
        // middleware flow into the handler (the property authentication relies on).
        $this->assertSame(200, $response->status());
        $this->assertSame('user:7|marker:app-kernel', $response->body());
    }

    #[Test]
    public function application_kernel_resolves_scoped_middleware_via_container(): void
    {
        $app = new Application('/tmp');
        $app->router()->get('/app-scoped', fn (Request $request): Response => new Response(
            'marker:' . ($request->getAttribute('marker') ?? 'missing')
        ), middleware: [AttributeCapturingMiddleware::class]);

        $response = $app->kernel()->handle(new Request('GET', '/app-scoped'));

        $this->assertSame(200, $response->status());
        $this->assertSame('marker:app-kernel', $response->body());
    }
}

final class BlockingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return new Response('blocked', 418);
    }
}

final class ExplodingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        throw new RuntimeException('scoped secret failure');
    }
}

final class AttributeCapturingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $request = $request->withAttribute('marker', 'app-kernel');

        return $next($request);
    }
}
