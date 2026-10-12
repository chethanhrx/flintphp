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
 * Orchestrates the request lifecycle: middleware → router → handler,
 * with a single exception boundary.
 */
final class Kernel
{
    private readonly ExceptionHandlerInterface $exceptionHandler;

    public function __construct(
        private readonly Router $router,
        private readonly MiddlewareStack $middlewareStack,
        private readonly HandlerInvoker $invoker,
        ?ExceptionHandlerInterface $exceptionHandler = null,
        private readonly ?ContainerInterface $container = null,
    ) {
        $this->exceptionHandler = $exceptionHandler ?? new \FlintPHP\Framework\Http\Exception\ExceptionHandler();
    }

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

                    return Response::error('Method Not Allowed', 405, 'method_not_allowed')
                        ->withHeader('Allow', $allowed);
                }

                return Response::error('Not Found', 404, 'not_found');
            };

            return $this->middlewareStack->handle($request, $terminalHandler);
        } catch (Throwable $exception) {
            return $this->exceptionHandler->handle($exception, $request);
        }
    }

    /**
     * @param array<string, string> $parameters
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
