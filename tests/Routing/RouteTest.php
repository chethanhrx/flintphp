<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Routing;

use FlintPHP\Framework\Routing\Route;
use FlintPHP\Framework\Routing\RoutingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
    // ---------------------------------------------------------------
    // Construction and accessors
    // ---------------------------------------------------------------

    #[Test]
    public function it_stores_method_pattern_handler_and_name(): void
    {
        $handler = fn() => 'hello';
        $route = new Route('GET', '/users', $handler, 'users.index');

        $this->assertSame('GET', $route->method());
        $this->assertSame('/users', $route->pattern());
        $this->assertSame($handler, $route->handler());
        $this->assertSame('users.index', $route->name());
    }

    #[Test]
    public function name_defaults_to_null(): void
    {
        $route = new Route('GET', '/', fn() => null);

        $this->assertNull($route->name());
    }

    // ---------------------------------------------------------------
    // Static vs dynamic
    // ---------------------------------------------------------------

    #[Test]
    public function static_route_has_no_parameters(): void
    {
        $route = new Route('GET', '/users', fn() => null);

        $this->assertTrue($route->isStatic());
        $this->assertSame([], $route->parameterNames());
    }

    #[Test]
    public function dynamic_route_has_parameters(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);

        $this->assertFalse($route->isStatic());
        $this->assertSame(['id'], $route->parameterNames());
    }

    #[Test]
    public function it_extracts_multiple_parameter_names(): void
    {
        $route = new Route('GET', '/users/{userId}/posts/{postId}', fn() => null);

        $this->assertSame(['userId', 'postId'], $route->parameterNames());
    }

    // ---------------------------------------------------------------
    // Matching — static routes
    // ---------------------------------------------------------------

    #[Test]
    public function static_route_matches_exact_path(): void
    {
        $route = new Route('GET', '/users', fn() => null);

        $this->assertSame([], $route->matches('/users'));
    }

    #[Test]
    public function static_route_does_not_match_different_path(): void
    {
        $route = new Route('GET', '/users', fn() => null);

        $this->assertNull($route->matches('/posts'));
        $this->assertNull($route->matches('/users/1'));
        $this->assertNull($route->matches('/'));
    }

    #[Test]
    public function root_static_route_matches(): void
    {
        $route = new Route('GET', '/', fn() => null);

        $this->assertSame([], $route->matches('/'));
        $this->assertNull($route->matches('/users'));
    }

    // ---------------------------------------------------------------
    // Matching — dynamic routes
    // ---------------------------------------------------------------

    #[Test]
    public function dynamic_route_matches_and_extracts_parameter(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);

        $params = $route->matches('/users/42');

        $this->assertSame(['id' => '42'], $params);
    }

    #[Test]
    public function dynamic_route_extracts_multiple_parameters(): void
    {
        $route = new Route('GET', '/users/{userId}/posts/{postId}', fn() => null);

        $params = $route->matches('/users/42/posts/99');

        $this->assertSame(['userId' => '42', 'postId' => '99'], $params);
    }

    #[Test]
    public function dynamic_route_does_not_match_missing_segment(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);

        $this->assertNull($route->matches('/users'));
        $this->assertNull($route->matches('/users/'));
    }

    #[Test]
    public function dynamic_route_does_not_match_extra_segments(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);

        $this->assertNull($route->matches('/users/42/extra'));
    }

    #[Test]
    public function parameter_values_are_strings(): void
    {
        $route = new Route('GET', '/items/{id}', fn() => null);

        $params = $route->matches('/items/42');

        $this->assertIsString($params['id']);
        $this->assertSame('42', $params['id']);
    }

    #[Test]
    public function parameter_does_not_match_across_slashes(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);

        // {id} should NOT match "42/posts" because [^/]+ stops at /
        $this->assertNull($route->matches('/users/42/posts'));
    }

    #[Test]
    public function parameter_matches_encoded_values(): void
    {
        $route = new Route('GET', '/search/{query}', fn() => null);

        $params = $route->matches('/search/hello%20world');

        // Raw URL-encoded value preserved — no double decoding
        $this->assertSame('hello%20world', $params['query']);
    }

    // ---------------------------------------------------------------
    // Validation — malformed patterns
    // ---------------------------------------------------------------

    #[Test]
    public function it_rejects_empty_parameter_names(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('empty parameter name');

        new Route('GET', '/users/{}', fn() => null);
    }

    #[Test]
    public function it_rejects_duplicate_parameter_names(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('duplicate parameter name');

        new Route('GET', '/users/{id}/posts/{id}', fn() => null);
    }

    #[Test]
    public function it_rejects_invalid_parameter_names(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('invalid parameter name');

        new Route('GET', '/users/{123invalid}', fn() => null);
    }

    #[Test]
    public function it_rejects_parameter_names_with_special_characters(): void
    {
        $this->expectException(RoutingException::class);

        new Route('GET', '/users/{user-id}', fn() => null);
    }

    #[Test]
    public function it_rejects_unmatched_opening_brace(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('unmatched opening brace');

        new Route('GET', '/users/{id', fn() => null);
    }

    #[Test]
    public function it_rejects_unmatched_closing_brace(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('unmatched closing brace');

        new Route('GET', '/users/id}', fn() => null);
    }

    #[Test]
    public function it_rejects_nested_braces(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('nested braces');

        new Route('GET', '/users/{{id}}', fn() => null);
    }

    #[Test]
    public function it_accepts_underscored_parameter_names(): void
    {
        $route = new Route('GET', '/users/{user_id}', fn() => null);

        $this->assertSame(['user_id'], $route->parameterNames());
    }

    #[Test]
    public function it_accepts_parameter_names_starting_with_underscore(): void
    {
        $route = new Route('GET', '/items/{_id}', fn() => null);

        $this->assertSame(['_id'], $route->parameterNames());
    }
}
