<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Middleware;

use FlintPHP\Framework\Authentication\AuthenticatorInterface;
use FlintPHP\Framework\Authentication\Exception\AuthenticationException;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;

final class RequireAuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthenticatorInterface $authenticator)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        try {
            $identity = $this->authenticator->authenticate($request);
            
            // Attach the identity to the request attributes so downstream controllers can use it
            $request = $request->withAttribute('identity', $identity);

            return $next($request);
        } catch (AuthenticationException $e) {
            // Short-circuit the request and return a 401 Unauthorized response
            return new Response(
                '{"error":"Unauthorized"}',
                401,
                [
                    'WWW-Authenticate' => 'Bearer',
                    'Content-Type' => 'application/json',
                ]
            );
        }
    }
}
