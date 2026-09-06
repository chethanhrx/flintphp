<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

use FlintPHP\Framework\Http\Exception\ExceptionHandlerInterface;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Route;
use FlintPHP\Framework\Routing\Router;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * The HTTP Kernel.
 *
 * The Kernel orchestrates the request lifecycle. It accepts an incoming
 * HTTP Request, passes it through the MiddlewareStack, matches a Route
 * using the Router, executes the matched handler, and returns an HTTP Response.
 *
 * It also defines the explicit framework HTTP exception boundary.
 */
final class Kernel
{
    private readonly ExceptionHandlerInterface $exceptionHandler;

    /**
     * @param Router                    $router           The routing engine.
     * @param MiddlewareStack           $middlewareStack  The middleware pipeline.
     * @param HandlerInvoker            $invoker          The handler dispatcher.
     * @param ExceptionHandlerInterface|null $exceptionHandler The exception boundary handler.
     * @param ContainerInterface|null   $container        Optional container used to resolve
     *                                                    route-scoped middleware class names.
     *                                                    When null, scoped middleware is
     *                                                    instantiated directly.
     */
    public function __construct(
        private readonly Router $router,
        private readonly MiddlewareStack $middlewareStack,
        private readonly HandlerInvoker $invoker,
        ?ExceptionHandlerInterface $exceptionHandler = null,
        private readonly ?ContainerInterface $container = null,
    ) {
        $this->exceptionHandler = $exceptionHandler ?? new \FlintPHP\Framework\Http\Exception\ExceptionHandler();
    }

    /**
     * Handle an incoming HTTP request and return a response.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        try {
            $terminalHandler = function (Request $req): Response {
                $result = $this->router->match($req);

                if ($result->isFound()) {
                    return $this->dispatchRoute($result->route(), $req, $result->parameters());
                }

                if ($result->isMethodNotAllowed()) {
                    $allowed = implode(', ', $result->allowedMethods());

                    return (new Response('Method Not Allowed', 405))
                        ->withHeader('Allow', $allowed);
                }

                return new Response('Not Found', 404);
            };

            return $this->middlewareStack->handle($request, $terminalHandler);
        } catch (Throwable $exception) {
            return $this->exceptionHandler->handle($exception, $request);
        }
    }

    /**
     * Dispatch a matched route through its route-scoped middleware pipeline.
     *
     * Routes without scoped middleware are invoked directly, preserving the
     * exact pre-existing dispatch path with zero added allocations.
     *
     * Scoped middleware runs inside the global pipeline and inside the
     * Kernel's exception boundary: a Throwable thrown by scoped middleware
     * reaches the ExceptionHandler exactly like any handler exception.
     *
     * @param Route                 $route      The matched route.
     * @param Request               $request    The current request.
     * @param array<string, string> $parameters Matched route parameters.
     */
    private function dispatchRoute(Route $route, Request $request, array $parameters): Response
    {
        $scopedMiddleware = $route->middleware();

        if ($scopedMiddleware === []) {
            return $this->invoker->invoke($route->handler(), $request, $parameters);
        }

        $resolved = [];

        foreach ($scopedMiddleware as $middlewareClass) {
            $middleware = $this->container !== null
                ? $this->container->get($middlewareClass)
                : new $middlewareClass();

            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'Route middleware %s must implement %s.',
                    $middlewareClass,
                    MiddlewareInterface::class
                ));
            }

            $resolved[] = $middleware;
        }

        $scopedStack = new MiddlewareStack($resolved);

        return $scopedStack->handle(
            $request,
            function (Request $req) use ($route, $parameters): Response {
                return $this->invoker->invoke($route->handler(), $req, $parameters);
            }
        );
    }
}
