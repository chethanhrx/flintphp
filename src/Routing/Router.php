<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

use FlintPHP\Framework\Http\Method;
use FlintPHP\Framework\Http\Request;

/**
 * HTTP router: registers routes and matches incoming requests.
 *
 * Matching algorithm:
 * 1. Static routes checked first via hash-map lookup (O(1)).
 * 2. Dynamic routes checked sequentially via compiled regex.
 * 3. If a path matches routes but not the method → METHOD_NOT_ALLOWED.
 * 4. If nothing matches → NOT_FOUND.
 *
 * Precedence: static routes always win over dynamic routes.
 *
 * The router does NOT execute handlers. It returns a RoutingResult
 * that the Kernel (v0.5.0) will use to dispatch the handler.
 */
final class Router
{
    /**
     * Static routes indexed by path and then method for O(1) lookup.
     * format: [path][method] = Route
     *
     * @var array<string, array<string, Route>>
     */
    private array $staticRoutes = [];

    /**
     * Dynamic routes (patterns with parameters), checked sequentially.
     *
     * @var Route[]
     */
    private array $dynamicRoutes = [];

    /**
     * All registered routes (for introspection).
     *
     * @var Route[]
     */
    private array $allRoutes = [];

    /**
     * Register a route.
     *
     * @param string  $method  HTTP method (e.g., 'GET', 'POST'). Case-insensitive.
     * @param string  $path    Route pattern (e.g., '/users/{id}').
     * @param mixed   $handler The handler (Closure, callable, etc.).
     * @param ?string $name    Optional route name.
     *
     * @throws RoutingException If the pattern is malformed.
     */
    public function add(string $method, string $path, mixed $handler, ?string $name = null): Route
    {
        $method = strtoupper($method);
        $route = new Route($method, $path, $handler, $name);

        if ($route->isStatic()) {
            if (!isset($this->staticRoutes[$path])) {
                $this->staticRoutes[$path] = [];
            }
            $this->staticRoutes[$path][$method] = $route;
        } else {
            // Unshift so that the last registered route takes precedence
            array_unshift($this->dynamicRoutes, $route);
        }

        $this->allRoutes[] = $route;

        return $route;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::GET->value, $path, $handler, $name);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::POST->value, $path, $handler, $name);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::PUT->value, $path, $handler, $name);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::PATCH->value, $path, $handler, $name);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::DELETE->value, $path, $handler, $name);
    }

    /**
     * Register a HEAD route.
     */
    public function head(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::HEAD->value, $path, $handler, $name);
    }

    /**
     * Register an OPTIONS route.
     */
    public function options(string $path, mixed $handler, ?string $name = null): Route
    {
        return $this->add(Method::OPTIONS->value, $path, $handler, $name);
    }

    /**
     * Match an incoming HTTP request to a registered route.
     *
     * Algorithm:
     * 1. Try static route lookup (O(1) hash map).
     * 2. Try dynamic routes (compiled regex, sequential).
     * 3. If the path matched routes but not with the right method → 405.
     * 4. If nothing matched → 404.
     */
    public function match(Request $request): RoutingResult
    {
        $method = $request->method();
        $path = $request->path();

        // 1. Static route lookup — O(1)
        if (isset($this->staticRoutes[$path])) {
            if (isset($this->staticRoutes[$path][$method])) {
                return RoutingResult::found($this->staticRoutes[$path][$method]);
            }

            // Path exists statically, but method differs
            $allowedMethods = array_keys($this->staticRoutes[$path]);
        } else {
            $allowedMethods = [];
        }

        // 2. Dynamic route matching
        foreach ($this->dynamicRoutes as $route) {
            $params = $route->matches($path);

            if ($params === null) {
                continue;
            }

            // Path matches — check method
            if ($route->method() === $method) {
                return RoutingResult::found($route, $params);
            }

            // Path matches but method differs — collect for 405
            $allowedMethods[] = $route->method();
        }

        if ($allowedMethods !== []) {
            return RoutingResult::methodNotAllowed(array_values(array_unique($allowedMethods)));
        }

        return RoutingResult::notFound();
    }

    /**
     * Get all registered routes.
     *
     * @return Route[]
     */
    public function routes(): array
    {
        return $this->allRoutes;
    }
}
