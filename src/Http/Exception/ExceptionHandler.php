<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use Throwable;

/**
 * Default exception handler with optional logging.
 *
 * - Unexpected Throwables → log at error, generic 500 response
 * - HttpException 4xx → optional info log (no stack)
 * - HttpException 5xx → log at error
 * - Never logs headers or body (token safety)
 */
final class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Throwable $exception, Request $request): Response
    {
        if ($exception instanceof HttpException) {
            $this->logHttpException($exception, $request);

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

        $this->logUnexpected($exception, $request);

        return (new Response(
            body: "Internal Server Error\n",
            status: 500,
        ))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function logHttpException(HttpException $exception, Request $request): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = [
            'exception' => $exception::class,
            'status' => $exception->status(),
            'method' => $request->method(),
            'path' => $request->path(),
        ];

        if ($exception->status() >= 500) {
            $this->logger->error($exception->getMessage() !== '' ? $exception->getMessage() : 'HTTP error', $context);
        } else {
            $this->logger->info($exception->getMessage() !== '' ? $exception->getMessage() : 'HTTP client error', $context);
        }
    }

    private function logUnexpected(Throwable $exception, Request $request): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->error($exception->getMessage() !== '' ? $exception->getMessage() : $exception::class, [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }
}
