<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

/**
 * The result of a route matching attempt.
 *
 * This is an immutable value object representing one of three outcomes:
 * - FOUND: a route matched the request
 * - NOT_FOUND: no route matches the requested path
 * - METHOD_NOT_ALLOWED: the path exists but the method is wrong
 *
 * Use the static factory methods to create instances:
 *   RoutingResult::found($route, $params)
 *   RoutingResult::notFound()
 *   RoutingResult::methodNotAllowed($allowedMethods)
 *
 * The future Kernel (v0.5.0) will inspect this result and produce
 * the appropriate HTTP response (200, 404, 405, etc.).
 */
final class RoutingResult
{
    /**
     * @param RoutingStatus        $status         The match status.
     * @param Route|null           $route          The matched route (FOUND only).
     * @param array<string, string> $parameters    Extracted route parameters.
     * @param string[]             $allowedMethods Allowed methods (METHOD_NOT_ALLOWED only).
     */
    private function __construct(
        private readonly RoutingStatus $status,
        private readonly ?Route $route,
        private readonly array $parameters,
        private readonly array $allowedMethods,
    ) {
    }

    /**
     * Create a FOUND result.
     *
     * @param Route                 $route      The matched route.
     * @param array<string, string> $parameters Extracted route parameters.
     */
    public static function found(Route $route, array $parameters = []): self
    {
        return new self(
            status: RoutingStatus::FOUND,
            route: $route,
            parameters: $parameters,
            allowedMethods: [],
        );
    }

    /**
     * Create a NOT_FOUND result.
     */
    public static function notFound(): self
    {
        return new self(
            status: RoutingStatus::NOT_FOUND,
            route: null,
            parameters: [],
            allowedMethods: [],
        );
    }

    /**
     * Create a METHOD_NOT_ALLOWED result.
     *
     * @param string[] $allowedMethods The HTTP methods allowed for the matched path.
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        return new self(
            status: RoutingStatus::METHOD_NOT_ALLOWED,
            route: null,
            parameters: [],
            allowedMethods: $allowedMethods,
        );
    }

    /**
     * Get the match status.
     */
    public function status(): RoutingStatus
    {
        return $this->status;
    }

    /**
     * Get the matched route (null if not found or method not allowed).
     */
    public function route(): ?Route
    {
        return $this->route;
    }

    /**
     * Get the matched route's handler (null if not found or method not allowed).
     */
    public function handler(): mixed
    {
        return $this->route?->handler();
    }

    /**
     * Get the extracted route parameters.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get the allowed HTTP methods (populated for METHOD_NOT_ALLOWED).
     *
     * @return string[]
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * Whether a matching route was found.
     */
    public function isFound(): bool
    {
        return $this->status === RoutingStatus::FOUND;
    }

    /**
     * Whether no route matched the path.
     */
    public function isNotFound(): bool
    {
        return $this->status === RoutingStatus::NOT_FOUND;
    }

    /**
     * Whether the path exists but the method is not allowed.
     */
    public function isMethodNotAllowed(): bool
    {
        return $this->status === RoutingStatus::METHOD_NOT_ALLOWED;
    }
}
