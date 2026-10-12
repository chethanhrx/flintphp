<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use Throwable;

/**
 * Exception handler that always returns JSON error bodies.
 *
 * Suitable for API applications. Unexpected failures still return a
 * generic 500 payload with no internal details. HttpException messages
 * are treated as client-safe public messages.
 *
 * Shape:
 * {"error":{"message":"...","status":400}}
 */
final class JsonExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof HttpException) {
            $message = $exception->getMessage();
            if ($message === '') {
                $message = sprintf('HTTP %d', $exception->status());
            }

            return Response::error($message, $exception->status());
        }

        return Response::error('Internal Server Error', 500);
    }
}
