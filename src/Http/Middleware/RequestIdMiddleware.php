<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Middleware;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;

/**
 * Ensures every request has an id (header + attribute) for log correlation.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'request_id';
    public const HEADER = 'X-Request-Id';

    public function process(Request $request, callable $next): Response
    {
        $id = $request->header(self::HEADER);
        if ($id === null || $id === '' || strlen($id) > 128 || preg_match('/\A[a-zA-Z0-9\-_]+\z/', $id) !== 1) {
            $id = bin2hex(random_bytes(16));
        }

        $request = $request->withAttribute(self::ATTRIBUTE, $id);
        $response = $next($request);

        return $response->withHeader(self::HEADER, $id);
    }
}
