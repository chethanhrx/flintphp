<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use LogicException;

/**
 * Validates synchronizer tokens on unsafe HTTP methods.
 *
 * Register after SessionMiddleware. Tokens are accepted from a configurable
 * request header; applications can render CsrfTokenManager::token($session)
 * into their forms and send it in that header.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CsrfTokenManager $tokens = new CsrfTokenManager(),
        private readonly string $headerName = 'X-CSRF-Token',
    ) {
        if (preg_match('/\A[A-Za-z0-9-]+\z/', $headerName) !== 1) {
            throw new \InvalidArgumentException('Invalid CSRF header name.');
        }
    }

    public function process(Request $request, callable $next): Response
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $session = $request->attribute(SessionMiddleware::ATTRIBUTE);

        if (!$session instanceof Session) {
            throw new LogicException('CsrfMiddleware must be registered after SessionMiddleware.');
        }

        if (!$this->tokens->isValid($session, $request->header($this->headerName))) {
            return Response::error('CSRF token missing or invalid.', 419, 'csrf_token_mismatch');
        }

        return $next($request);
    }
}
