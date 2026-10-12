<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use Throwable;

/**
 * Default exception handler.
 *
 * - Unexpected failures → generic 500, no internal disclosure
 * - HttpException → public message only, with text/plain + nosniff
 *
 * Prefer JsonExceptionHandler for API applications:
 *   $app->setExceptionHandler(new JsonExceptionHandler());
 */
final class ExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof HttpException) {
            $message = $exception->getMessage();
            if ($message === '') {
                $message = sprintf('HTTP %d', $exception->status());
            }

            return (new Response(
                body: $message . "\n",
                status: $exception->status(),
            ))
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        }

        return (new Response(
            body: "Internal Server Error\n",
            status: 500,
        ))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
