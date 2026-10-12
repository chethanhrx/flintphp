<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Routing;

use LogicException;

/**
 * An immutable route definition.
 */
final class Route
{
    /** @var string[] */
    private readonly array $parameterNames;

    private readonly string $regex;

    private readonly bool $static;

    /** @var string[] */
    private readonly array $middleware;

    public function __construct(
        private readonly string $method,
        private readonly string $pattern,
        private readonly mixed $handler,
        private readonly ?string $name = null,
        array $middleware = [],
    ) {
        if (!str_starts_with($pattern, '/')) {
            throw new RoutingException(
                sprintf('Route pattern "%s" must start with a forward slash (/).', $pattern),
            );
        }

        foreach ($middleware as $middlewareClass) {
            if (!is_string($middlewareClass) || $middlewareClass === '') {
                throw new RoutingException(
                    sprintf(
                        'Route "%s" middleware must be non-empty class-name strings. Got: %s',
                        $pattern,
                        get_debug_type($middlewareClass),
                    ),
                );
            }
        }

        $this->middleware = array_values($middleware);

        [$this->regex, $this->parameterNames, $this->static] = self::compile($pattern);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    /** @return string[] */
    public function parameterNames(): array
    {
        return $this->parameterNames;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    /** @return string[] */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * @deprecated Do not use. The returned Route is NOT registered with the Router,
     * so middleware would be silently ignored — a security trap.
     *
     * Register middleware at registration time instead:
     *   $router->get('/path', $handler, middleware: [MyMiddleware::class]);
     *
     * @throws LogicException Always
     */
    public function withMiddleware(array $middleware): self
    {
        throw new LogicException(
            'Route::withMiddleware() is not supported. '
            . 'The Route is immutable and the Router never receives the derived instance, '
            . 'so middleware would be silently skipped. '
            . 'Pass middleware at registration time: '
            . '$router->get($path, $handler, middleware: [MyMiddleware::class]).'
        );
    }

    /**
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
     * @return array{string, string[], bool}
     */
    private static function compile(string $pattern): array
    {
        self::validateBraces($pattern);

        if (preg_match_all('/\{([^}]*)\}/', $pattern, $placeholders) === 0) {
            return ['', [], true];
        }

        $parameterNames = [];

        foreach ($placeholders[1] as $paramName) {
            if ($paramName === '') {
                throw new RoutingException(
                    sprintf('Route pattern "%s" contains an empty parameter name.', $pattern),
                );
            }

            if (preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $paramName) !== 1) {
                throw new RoutingException(
                    sprintf(
                        'Route pattern "%s" contains invalid parameter name "%s".',
                        $pattern,
                        $paramName,
                    ),
                );
            }

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

        $segments = preg_split('/(\{[^}]+\})/', $pattern, flags: PREG_SPLIT_DELIM_CAPTURE);

        $regex = '~\A';
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $regex .= '([^/]+)';
            } else {
                $regex .= preg_quote($segment, '~');
            }
        }
        $regex .= '\z~';

        return [$regex, $parameterNames, false];
    }

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
