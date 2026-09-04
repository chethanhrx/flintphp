<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Middleware;

use Closure;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use InvalidArgumentException;

/**
 * Composes a pipeline of MiddlewareInterface instances around a final handler.
 *
 * Middleware executes in a strict "onion" order:
 * A (before) -> B (before) -> Handler -> B (after) -> A (after)
 *
 * The internal middleware list is immutable after construction.
 */
final class MiddlewareStack
{
    /**
     * @var MiddlewareInterface[]
     */
    private readonly array $middlewares;

    /**
     * @param array<mixed> $middlewares An array of MiddlewareInterface instances.
     *
     * @throws InvalidArgumentException If any element is not a MiddlewareInterface.
     */
    public function __construct(array $middlewares = [])
    {
        $validMiddlewares = [];

        foreach ($middlewares as $index => $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Middleware at index %s must implement %s. Got: %s',
                        $index,
                        MiddlewareInterface::class,
                        get_debug_type($middleware),
                    ),
                );
            }

            $validMiddlewares[] = $middleware;
        }

        // Re-key and assign to readonly property, ensuring external arrays
        // cannot mutate the internal state by reference.
        $this->middlewares = $validMiddlewares;
    }

    /**
     * Handle the request by passing it through the middleware stack to the handler.
     *
     * @param Request  $request The incoming request.
     * @param callable $handler The final fallback handler, signature: fn(Request): Response
     *
     * @return Response
     */
    public function handle(Request $request, callable $handler): Response
    {
        $pipeline = $this->buildPipeline($handler);

        return $pipeline($request);
    }

    /**
     * Build the pipeline of closures from the inside out.
     */
    private function buildPipeline(callable $handler): Closure
    {
        // The base of the pipeline is the final handler.
        // We wrap it in a closure to enforce the signature strictly internally.
        $next = function (Request $request) use ($handler): Response {
            return $handler($request);
        };

        // Wrap the handler with middleware in reverse order.
        // The last middleware in the array wraps the handler directly.
        // The first middleware in the array becomes the outermost wrapper.
        for ($i = count($this->middlewares) - 1; $i >= 0; $i--) {
            $middleware = $this->middlewares[$i];

            $next = function (Request $request) use ($middleware, $next): Response {
                return $middleware->process($request, $next);
            };
        }

        return $next;
    }
}
