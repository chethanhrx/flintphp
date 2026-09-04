<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

/**
 * An immutable route definition.
 *
 * Represents a single registered route with its HTTP method, path
 * pattern, handler, and compiled regex. Route patterns are compiled
 * to regex at construction time — never at match time.
 *
 * Parameter placeholders use `{name}` syntax:
 *   /users/{id}
 *   /users/{userId}/posts/{postId}
 *
 * Parameter names must be valid PHP identifiers: [a-zA-Z_][a-zA-Z0-9_]*
 *
 * Static routes (no parameters) are flagged for O(1) hash-map lookup
 * in the Router.
 */
final class Route
{
    /** @var string[] */
    private readonly array $parameterNames;

    private readonly string $regex;

    private readonly bool $static;

    /**
     * @param string  $method  The HTTP method (uppercase).
     * @param string  $pattern The path pattern (e.g., '/users/{id}').
     * @param mixed   $handler The route handler (Closure, callable, etc.).
     * @param ?string $name    Optional route name.
     *
     * @throws RoutingException If the pattern is malformed.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $pattern,
        private readonly mixed $handler,
        private readonly ?string $name = null,
    ) {
        if (!str_starts_with($pattern, '/')) {
            throw new RoutingException(
                sprintf('Route pattern "%s" must start with a forward slash (/).', $pattern),
            );
        }

        [$this->regex, $this->parameterNames, $this->static] = self::compile($pattern);
    }


    /**
     * Get the HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the original path pattern.
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the route handler.
     */
    public function handler(): mixed
    {
        return $this->handler;
    }

    /**
     * Get the route name, if any.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Get the parameter names extracted from the pattern.
     *
     * @return string[]
     */
    public function parameterNames(): array
    {
        return $this->parameterNames;
    }

    /**
     * Whether this route is static (has no parameters).
     */
    public function isStatic(): bool
    {
        return $this->static;
    }

    /**
     * Attempt to match a request path against this route.
     *
     * Returns an associative array of parameter values on success,
     * or null if the path does not match.
     *
     * For static routes, returns an empty array on exact match.
     *
     * @param string $path The request path (not decoded).
     * @return array<string, string>|null
     */
    public function matches(string $path): ?array
    {
        if ($this->static) {
            return $path === $this->pattern ? [] : null;
        }

        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($this->parameterNames as $i => $name) {
            $params[$name] = $matches[$i + 1];
        }

        return $params;
    }

    /**
     * Compile a route pattern into a regex, parameter names, and static flag.
     *
     * @return array{string, string[], bool}
     * @throws RoutingException
     */
    private static function compile(string $pattern): array
    {
        // Detect malformed braces: unmatched { or }
        self::validateBraces($pattern);

        // Extract parameter placeholders
        if (preg_match_all('/\{([^}]*)\}/', $pattern, $placeholders) === 0) {
            // No parameters — static route
            return ['', [], true];
        }

        $parameterNames = [];

        foreach ($placeholders[1] as $paramName) {
            // Empty parameter name: /{}/
            if ($paramName === '') {
                throw new RoutingException(
                    sprintf('Route pattern "%s" contains an empty parameter name.', $pattern),
                );
            }

            // Invalid identifier
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $paramName) !== 1) {
                throw new RoutingException(
                    sprintf(
                        'Route pattern "%s" contains invalid parameter name "%s". '
                        . 'Parameter names must be valid identifiers: [a-zA-Z_][a-zA-Z0-9_]*.',
                        $pattern,
                        $paramName,
                    ),
                );
            }

            // Duplicate parameter name
            if (in_array($paramName, $parameterNames, true)) {
                throw new RoutingException(
                    sprintf(
                        'Route pattern "%s" contains duplicate parameter name "%s".',
                        $pattern,
                        $paramName,
                    ),
                );
            }

            $parameterNames[] = $paramName;
        }

        // Build regex: escape static segments, replace {param} with capture groups.
        // Split on parameter placeholders while preserving them.
        $segments = preg_split('/(\{[^}]+\})/', $pattern, flags: PREG_SPLIT_DELIM_CAPTURE);

        $regex = '~^';
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                // Parameter placeholder → capture group (matches one or more non-slash chars)
                $regex .= '([^/]+)';
            } else {
                // Static segment → escape for regex safety
                $regex .= preg_quote($segment, '~');
            }
        }
        $regex .= '$~';

        return [$regex, $parameterNames, false];
    }

    /**
     * Validate that braces are properly paired in the pattern.
     *
     * @throws RoutingException
     */
    private static function validateBraces(string $pattern): void
    {
        $depth = 0;

        for ($i = 0, $len = strlen($pattern); $i < $len; $i++) {
            if ($pattern[$i] === '{') {
                $depth++;
                if ($depth > 1) {
                    throw new RoutingException(
                        sprintf('Route pattern "%s" contains nested braces.', $pattern),
                    );
                }
            } elseif ($pattern[$i] === '}') {
                $depth--;
                if ($depth < 0) {
                    throw new RoutingException(
                        sprintf('Route pattern "%s" contains an unmatched closing brace.', $pattern),
                    );
                }
            }
        }

        if ($depth !== 0) {
            throw new RoutingException(
                sprintf('Route pattern "%s" contains an unmatched opening brace.', $pattern),
            );
        }
    }
}
