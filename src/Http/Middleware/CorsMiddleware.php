<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Http\Middleware;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use InvalidArgumentException;

/**
 * Hardened CORS middleware.
 *
 * Security and protocol specifications:
 * - Origins must match exactly, including scheme, host, and port (e.g. "https://app.example.com", "http://localhost:3000").
 * - Wildcard allowedOrigins ("*") cannot be combined with allowCredentials = true (violates Fetch spec and prevents credential reflection attacks).
 * - Never reflects arbitrary unvalidated origins.
 * - Disallowed origins receive no permissive CORS headers.
 * - Existing response headers and Vary headers are preserved.
 * - Preflight (OPTIONS) requests validate Access-Control-Request-Method and Access-Control-Request-Headers.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins Exact origins, or ['*'] for any origin without credentials
     * @param list<string> $allowedMethods Allowed HTTP methods
     * @param list<string> $allowedHeaders Allowed request headers
     * @param int $maxAge Preflight cache duration in seconds
     * @param bool $allowCredentials Whether to allow credentials (cookies, authorization headers)
     */
    public function __construct(
        private readonly array $allowedOrigins = ['*'],
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Request-Id'],
        private readonly int $maxAge = 86400,
        private readonly bool $allowCredentials = false,
    ) {
        if ($this->maxAge < 0) {
            throw new InvalidArgumentException('maxAge must be >= 0');
        }

        if ($this->allowedOrigins === []) {
            throw new InvalidArgumentException('allowedOrigins must not be empty');
        }

        foreach ($this->allowedOrigins as $origin) {
            if (!is_string($origin) || trim($origin) === '') {
                throw new InvalidArgumentException('allowedOrigins must not contain empty or whitespace-only origins');
            }
        }

        if ($this->allowCredentials && in_array('*', $this->allowedOrigins, true)) {
            throw new InvalidArgumentException(
                'Cannot combine wildcard allowedOrigins ["*"] with allowCredentials = true. Specify explicit allowed origins.'
            );
        }

        if ($this->allowedMethods === []) {
            throw new InvalidArgumentException('allowedMethods must not be empty');
        }

        foreach ($this->allowedMethods as $method) {
            if (!is_string($method) || trim($method) === '') {
                throw new InvalidArgumentException('allowedMethods must not contain empty methods');
            }
        }
    }

    public function process(Request $request, callable $next): Response
    {
        $origin = $request->header('Origin');

        // Check if this is an explicit CORS preflight request
        $isPreflight = $request->method() === 'OPTIONS'
            && $origin !== null
            && $request->header('Access-Control-Request-Method') !== null;

        if ($isPreflight) {
            return $this->handlePreflight($request, $origin);
        }

        $response = $next($request);

        if ($origin === null || !$this->isOriginAllowed($origin)) {
            return $response;
        }

        return $this->addCorsHeaders($response, $origin);
    }

    private function handlePreflight(Request $request, string $origin): Response
    {
        if (!$this->isOriginAllowed($origin)) {
            return new Response('', 403);
        }

        $requestedMethod = strtoupper(trim((string) $request->header('Access-Control-Request-Method')));
        $upperAllowedMethods = array_map('strtoupper', $this->allowedMethods);

        if (!in_array($requestedMethod, $upperAllowedMethods, true)) {
            return new Response('', 403);
        }

        $requestedHeadersHeader = $request->header('Access-Control-Request-Headers');
        if ($requestedHeadersHeader !== null && trim($requestedHeadersHeader) !== '') {
            $requestedHeaders = array_map('strtolower', array_map('trim', explode(',', $requestedHeadersHeader)));
            $allowedHeadersLower = array_map('strtolower', $this->allowedHeaders);

            if (!in_array('*', $allowedHeadersLower, true)) {
                foreach ($requestedHeaders as $reqHeader) {
                    if ($reqHeader !== '' && !in_array($reqHeader, $allowedHeadersLower, true)) {
                        return new Response('', 403);
                    }
                }
            }
        }

        $response = new Response('', 204);

        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        $allowOrigin = in_array('*', $this->allowedOrigins, true) && !$this->allowCredentials
            ? '*'
            : $origin;

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Preserve existing Vary header while adding Origin
        $existingVary = $response->header('Vary');
        if ($existingVary !== null && trim($existingVary) !== '') {
            $varyTokens = array_map('trim', explode(',', $existingVary));
            if (!in_array('Origin', $varyTokens, true)) {
                $response = $response->withHeader('Vary', $existingVary . ', Origin');
            }
        } else {
            $response = $response->withHeader('Vary', 'Origin');
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
