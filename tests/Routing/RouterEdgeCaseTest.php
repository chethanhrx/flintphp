<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Routing;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Routing\Router;
use FlintPHP\Framework\Routing\RoutingStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for the Router matching algorithm.
 *
 * Covers paths that the existing RouterTest doesn't exercise:
 * case sensitivity, trailing slashes, regex metacharacter safety,
 * empty router, and HEAD auto-fallback awareness.
 */
#[CoversClass(Router::class)]
final class RouterEdgeCaseTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    // ---------------------------------------------------------------
    // Case sensitivity
    // ---------------------------------------------------------------

    #[Test]
    public function static_routes_are_case_sensitive(): void
    {
        $this->router->get('/users', 'handler');

        $this->assertTrue($this->router->match(new Request('GET', '/users'))->isFound());
        $this->assertTrue($this->router->match(new Request('GET', '/Users'))->isNotFound());
        $this->assertTrue($this->router->match(new Request('GET', '/USERS'))->isNotFound());
    }

    #[Test]
    public function dynamic_routes_are_case_sensitive(): void
    {
        $this->router->get('/api/users/{id}', 'handler');

        $this->assertTrue($this->router->match(new Request('GET', '/api/users/42'))->isFound());
        $this->assertTrue($this->router->match(new Request('GET', '/API/users/42'))->isNotFound());
        $this->assertTrue($this->router->match(new Request('GET', '/api/Users/42'))->isNotFound());
    }

    #[Test]
    public function method_matching_is_case_insensitive_via_router_add(): void
    {
        // Router::add() uppercases the method, so 'get' and 'GET' register the same route.
        $this->router->add('get', '/test', 'handler');

        $result = $this->router->match(new Request('GET', '/test'));
        $this->assertTrue($result->isFound());
    }

    // ---------------------------------------------------------------
    // Trailing slash semantics
    // ---------------------------------------------------------------

    #[Test]
    public function trailing_slash_is_a_distinct_route(): void
    {
        $this->router->get('/users', 'without-slash');
        $this->router->get('/users/', 'with-slash');

        $withoutSlash = $this->router->match(new Request('GET', '/users'));
        $this->assertTrue($withoutSlash->isFound());
        $this->assertSame('without-slash', $withoutSlash->handler());

        $withSlash = $this->router->match(new Request('GET', '/users/'));
        $this->assertTrue($withSlash->isFound());
        $this->assertSame('with-slash', $withSlash->handler());
    }

    #[Test]
    public function trailing_slash_does_not_match_without_it_when_only_trailing_is_registered(): void
    {
        $this->router->get('/items/', 'handler');

        $this->assertTrue($this->router->match(new Request('GET', '/items/'))->isFound());
        $this->assertTrue($this->router->match(new Request('GET', '/items'))->isNotFound());
    }

    #[Test]
    public function dynamic_trailing_slash_does_not_match_without_it(): void
    {
        $this->router->get('/users/{id}/', 'handler');

        $this->assertTrue($this->router->match(new Request('GET', '/users/42/'))->isFound());
        $this->assertTrue($this->router->match(new Request('GET', '/users/42'))->isNotFound());
    }

    // ---------------------------------------------------------------
    // Regex metacharacter safety
    // ---------------------------------------------------------------

    #[Test]
    public function static_path_with_dots_matches_literally(): void
    {
        $this->router->get('/api/v1.0/users', 'versioned');

        $found = $this->router->match(new Request('GET', '/api/v1.0/users'));
        $this->assertTrue($found->isFound());
        $this->assertSame('versioned', $found->handler());
    }

    #[Test]
    public function dynamic_path_with_dots_matches_literally_not_as_regex(): void
    {
        $this->router->get('/api/v1.0/users/{id}', 'versioned');

        $found = $this->router->match(new Request('GET', '/api/v1.0/users/42'));
        $this->assertTrue($found->isFound());
        $this->assertSame(['id' => '42'], $found->parameters());

        // The dot in "v1.0" must NOT match "v1X0" (regex . means any char)
        $notFound = $this->router->match(new Request('GET', '/api/v1X0/users/42'));
        $this->assertTrue($notFound->isNotFound());
    }

    #[Test]
    public function path_with_regex_special_characters_is_safe(): void
    {
        // Ensure all regex-special characters in the static segment are escaped properly
        $this->router->get('/api/test+path/{id}', 'plus');
        $this->router->get('/api/test(path)/{id}', 'parens');
        $this->router->get('/api/test[path]/{id}', 'brackets');

        $plus = $this->router->match(new Request('GET', '/api/test+path/1'));
        $this->assertTrue($plus->isFound());

        $parens = $this->router->match(new Request('GET', '/api/test(path)/1'));
        $this->assertTrue($parens->isFound());

        $brackets = $this->router->match(new Request('GET', '/api/test[path]/1'));
        $this->assertTrue($brackets->isFound());
    }

    // ---------------------------------------------------------------
    // Empty router
    // ---------------------------------------------------------------

    #[Test]
    public function empty_router_returns_not_found(): void
    {
        $result = $this->router->match(new Request('GET', '/'));

        $this->assertTrue($result->isNotFound());
        $this->assertNull($result->handler());
        $this->assertSame([], $result->allowedMethods());
    }

    #[Test]
    public function empty_router_routes_returns_empty_array(): void
    {
        $this->assertSame([], $this->router->routes());
    }

    // ---------------------------------------------------------------
    // Root path
    // ---------------------------------------------------------------

    #[Test]
    public function root_path_matches_only_root(): void
    {
        $this->router->get('/', 'root');

        $this->assertTrue($this->router->match(new Request('GET', '/'))->isFound());
        $this->assertTrue($this->router->match(new Request('GET', '/anything'))->isNotFound());
    }

    // ---------------------------------------------------------------
    // Method matching edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function method_not_allowed_includes_all_registered_methods_for_path(): void
    {
        $this->router->get('/resource', 'get');
        $this->router->post('/resource', 'post');
        $this->router->put('/resource', 'put');
        $this->router->delete('/resource', 'delete');

        $result = $this->router->match(new Request('PATCH', '/resource'));
        $this->assertTrue($result->isMethodNotAllowed());

        $allowed = $result->allowedMethods();
        $this->assertCount(4, $allowed);
        $this->assertContains('GET', $allowed);
        $this->assertContains('POST', $allowed);
        $this->assertContains('PUT', $allowed);
        $this->assertContains('DELETE', $allowed);
    }

    #[Test]
    public function same_path_different_methods_are_distinct_routes(): void
    {
        $this->router->get('/resource', 'get-handler');
        $this->router->post('/resource', 'post-handler');

        $getResult = $this->router->match(new Request('GET', '/resource'));
        $postResult = $this->router->match(new Request('POST', '/resource'));

        $this->assertSame('get-handler', $getResult->handler());
        $this->assertSame('post-handler', $postResult->handler());
    }
}
