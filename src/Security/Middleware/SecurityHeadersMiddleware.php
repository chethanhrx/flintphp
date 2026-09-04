<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Security\Middleware;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SecurityHeadersConfiguration $config
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$response->headers()->has('X-Content-Type-Options')) {
            $response = $response->withHeader('X-Content-Type-Options', $this->config->xContentTypeOptions);
        }

        if (!$response->headers()->has('X-Frame-Options')) {
            $response = $response->withHeader('X-Frame-Options', $this->config->xFrameOptions);
        }

        if (!$response->headers()->has('Referrer-Policy')) {
            $response = $response->withHeader('Referrer-Policy', $this->config->referrerPolicy);
        }

        if ($this->config->contentSecurityPolicy !== null && !$response->headers()->has('Content-Security-Policy')) {
            $response = $response->withHeader('Content-Security-Policy', $this->config->contentSecurityPolicy);
        }

        if ($this->config->strictTransportSecurity !== null && !$response->headers()->has('Strict-Transport-Security')) {
            $response = $response->withHeader('Strict-Transport-Security', $this->config->strictTransportSecurity);
        }

        return $response;
    }
}
