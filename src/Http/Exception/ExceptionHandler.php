<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use Throwable;

/**
 * The default exception handler.
 *
 * Provides a generic, production-safe HTTP 500 response.
 * It intentionally obscures exception details (message, class, stack trace)
 * to prevent leaking sensitive information to clients.
 */
final class ExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(Throwable $exception, Request $request): Response
    {
        return new Response(
            body: "Internal Server Error\n",
            status: 500,
        );
    }
}
