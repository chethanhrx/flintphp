<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Routing;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Routing\Route;
use FlintPHP\Framework\Routing\Router;
use FlintPHP\Framework\Routing\RoutingStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    // ---------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------

    #[Test]
    public function it_registers_routes_via_add(): void
    {
        $route = $this->router->add('GET', '/users', 'handler_func');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('GET', $route->method());
        $this->assertSame('/users', $route->pattern());
        $this->assertSame('handler_func', $route->handler());

        $this->assertCount(1, $this->router->routes());
    }

    #[Test]
    public function it_registers_routes_via_convenience_methods(): void
    {
        $this->router->get('/get', 'h1');
        $this->router->post('/post', 'h2');
        $this->router->put('/put', 'h3');
        $this->router->patch('/patch', 'h4');
        $this->router->delete('/delete', 'h5');
        $this->router->head('/head', 'h6');
        $this->router->options('/options', 'h7');

        $routes = $this->router->routes();
        $this->assertCount(7, $routes);

        $methods = array_map(fn(Route $r) => $r->method(), $routes);
        $this->assertSame(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], $methods);
    }

    // ---------------------------------------------------------------
    // Matching — Found
    // ---------------------------------------------------------------

    #[Test]
    public function it_matches_static_route(): void
    {
        $this->router->get('/users', 'users.index');

        $request = new Request('GET', '/users');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        $this->assertSame('users.index', $result->handler());
        $this->assertSame([], $result->parameters());
    }

    #[Test]
    public function it_matches_dynamic_route(): void
    {
        $this->router->get('/users/{id}', 'users.show');

        $request = new Request('GET', '/users/42');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        $this->assertSame('users.show', $result->handler());
        $this->assertSame(['id' => '42'], $result->parameters());
    }

    #[Test]
    public function static_route_takes_precedence_over_dynamic_route(): void
    {
        // Even if dynamic is registered first
        $this->router->get('/users/{id}', 'dynamic');
        $this->router->get('/users/new', 'static');

        $request = new Request('GET', '/users/new');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        // Should match static, not the dynamic route where id='new'
        $this->assertSame('static', $result->handler());
    }

    #[Test]
    public function duplicate_static_routes_last_registered_wins(): void
    {
        $this->router->get('/users', 'first');
        $this->router->get('/users', 'second');

        $request = new Request('GET', '/users');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        $this->assertSame('second', $result->handler());
    }

    #[Test]
    public function duplicate_dynamic_routes_last_registered_wins(): void
    {
        $this->router->get('/users/{id}', 'first');
        $this->router->get('/users/{slug}', 'second');

        $request = new Request('GET', '/users/42');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        $this->assertSame('second', $result->handler());
        // Second route used {slug} as parameter name
        $this->assertSame(['slug' => '42'], $result->parameters());
    }

    #[Test]
    public function it_supports_custom_http_methods(): void
    {
        $this->router->add('PURGE', '/cache', 'purge.handler');

        $request = new Request('PURGE', '/cache');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        $this->assertSame('purge.handler', $result->handler());
    }

    // ---------------------------------------------------------------
    // Matching — Not Found
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_not_found_when_no_routes_match(): void
    {
        $this->router->get('/users', 'users.index');

        $request = new Request('GET', '/posts');
        $result = $this->router->match($request);

        $this->assertTrue($result->isNotFound());
        $this->assertNull($result->handler());
    }

    // ---------------------------------------------------------------
    // Matching — Method Not Allowed
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_method_not_allowed_for_static_route(): void
    {
        $this->router->get('/users', 'get.users');
        $this->router->post('/users', 'post.users');

        // Request PUT on /users
        $request = new Request('PUT', '/users');
        $result = $this->router->match($request);

        $this->assertTrue($result->isMethodNotAllowed());
        $this->assertNull($result->handler());
        
        // Order is not guaranteed, but both should be present
        $allowed = $result->allowedMethods();
        $this->assertCount(2, $allowed);
        $this->assertContains('GET', $allowed);
        $this->assertContains('POST', $allowed);
    }

    #[Test]
    public function it_returns_method_not_allowed_for_dynamic_route(): void
    {
        $this->router->get('/users/{id}', 'get.user');
        $this->router->delete('/users/{id}', 'delete.user');

        $request = new Request('PATCH', '/users/42');
        $result = $this->router->match($request);

        $this->assertTrue($result->isMethodNotAllowed());
        
        $allowed = $result->allowedMethods();
        $this->assertCount(2, $allowed);
        $this->assertContains('GET', $allowed);
        $this->assertContains('DELETE', $allowed);
    }

    #[Test]
    public function it_combines_allowed_methods_from_static_and_dynamic_routes(): void
    {
        // This is a weird edge case, but the router should handle it predictably.
        // E.g. A static POST route and a dynamic GET route that could match the same path.
        $this->router->post('/items/42', 'static.post');
        $this->router->get('/items/{id}', 'dynamic.get');

        $request = new Request('PUT', '/items/42');
        $result = $this->router->match($request);

        $this->assertTrue($result->isMethodNotAllowed());

        $allowed = $result->allowedMethods();
        $this->assertCount(2, $allowed);
        $this->assertContains('POST', $allowed);
        $this->assertContains('GET', $allowed);
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function it_ignores_query_string_when_matching(): void
    {
        $this->router->get('/users', 'users.index');

        $request = new Request('GET', '/users?active=1&page=2');
        $result = $this->router->match($request);

        $this->assertTrue($result->isFound());
        $this->assertSame('users.index', $result->handler());
    }

    #[Test]
    public function it_does_not_execute_handlers(): void
    {
        $executed = false;
        $handler = function () use (&$executed) {
            $executed = true;
        };

        $this->router->get('/test', $handler);
        $result = $this->router->match(new Request('GET', '/test'));

        $this->assertTrue($result->isFound());
        $this->assertFalse($executed, 'Router should not execute the handler');
    }
}
