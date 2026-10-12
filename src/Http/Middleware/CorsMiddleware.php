<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Middleware;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;

/**
 * Explicit CORS middleware. Configure allowed origins; no "*" + credentials.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins Exact origins, or ['*'] for any (no credentials)
     * @param list<string> $allowedMethods
     * @param list<string> $allowedHeaders
     */
    public function __construct(
        private readonly array $allowedOrigins = ['*'],
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Request-Id'],
        private readonly int $maxAge = 86400,
        private readonly bool $allowCredentials = false,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin');

        if ($request->method() === 'OPTIONS') {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }

        if ($origin === null) {
            return $response;
        }

        if (!$this->isOriginAllowed($origin)) {
            return $response;
        }

        $allowOrigin = in_array('*', $this->allowedOrigins, true) && !$this->allowCredentials
            ? '*'
            : $origin;

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge)
            ->withHeader('Vary', 'Origin');

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }
}
