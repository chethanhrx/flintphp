<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Exception;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use Throwable;

/**
 * Defines the contract for the framework's HTTP exception boundary.
 */
interface ExceptionHandlerInterface
{
    /**
     * Convert an exception into an HTTP Response.
     *
     * The exception handler must NOT mutate the Request, execute the exception,
     * or recursively invoke itself.
     *
     * @param Throwable $exception The thrown exception.
     * @param Request   $request   The exact Request instance being handled.
     *
     * @return Response The generated HTTP Response (typically 500).
     */
    public function handle(Throwable $exception, Request $request): Response;
}
