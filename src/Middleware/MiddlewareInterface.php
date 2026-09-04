<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Middleware;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

/**
 * Minimal contract for a framework middleware.
 *
 * Middleware forms an "onion" pipeline around the final request handler.
 * Each middleware can inspect or modify the request before passing it to
 * the next layer, and can inspect or modify the response on its way out.
 *
 * Middleware may short-circuit the pipeline by returning a Response
 * directly without calling $next.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * @param Request  $request The incoming HTTP request.
     * @param callable $next    The next middleware or final handler in the stack.
     *                          Must have signature: `fn(Request): Response`
     *
     * @return Response
     */
    public function process(Request $request, callable $next): Response;
}
