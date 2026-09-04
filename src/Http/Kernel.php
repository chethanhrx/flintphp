<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http;

use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Routing\Router;

/**
 * The HTTP Kernel.
 *
 * The Kernel orchestrates the request lifecycle. It accepts an incoming
 * HTTP Request, passes it through the MiddlewareStack, matches a Route
 * using the Router, executes the matched handler, and returns an HTTP Response.
 */
final class Kernel
{
    /**
     * @param Router          $router          The routing engine.
     * @param MiddlewareStack $middlewareStack The middleware pipeline.
     * @param HandlerInvoker  $invoker         The handler dispatcher.
     */
    public function __construct(
        private readonly Router $router,
        private readonly MiddlewareStack $middlewareStack,
        private readonly HandlerInvoker $invoker,
    ) {
    }

    /**
     * Handle an incoming HTTP request and return a response.
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $terminalHandler = function (Request $req): Response {
            $result = $this->router->match($req);

            if ($result->isFound()) {
                return $this->invoker->invoke($result->handler(), $req, $result->parameters());
            }

            if ($result->isMethodNotAllowed()) {
                $allowed = implode(', ', $result->allowedMethods());

                return (new Response('Method Not Allowed', 405))
                    ->withHeader('Allow', $allowed);
            }

            return new Response('Not Found', 404);
        };

        return $this->middlewareStack->handle($request, $terminalHandler);
    }
}
