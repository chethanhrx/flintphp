<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use Throwable;

/**
 * JSON exception handler with optional logging (same rules as ExceptionHandler).
 */
final class JsonExceptionHandler implements ExceptionHandlerInterface
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

            return Response::error($message, $exception->status());
        }

        $this->logUnexpected($exception, $request);

        return Response::error('Internal Server Error', 500);
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
