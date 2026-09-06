<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authorization\Middleware;

use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authorization\AuthorizerInterface;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use LogicException;

/**
 * Enforces an authorization decision for the current request.
 *
 * Reads the authenticated identity from the Request attribute named by
 * IDENTITY_ATTRIBUTE (populated by RequireAuthenticationMiddleware), asks the
 * bound AuthorizerInterface for a boolean decision, and:
 * - on allow: passes the request through unchanged,
 * - on deny: short-circuits with a fixed 403 JSON response. The response
 *   intentionally contains no WWW-Authenticate header (that would advertise
 *   an authentication problem, not an authorization denial) and no internal
 *   details.
 *
 * Failure semantics (fail-closed):
 * - An exception thrown by the authorizer propagates to the Kernel's
 *   exception boundary; the request never proceeds. Exceptions are never
 *   converted into denials, and failures never result in allowances.
 * - A non-IdentityInterface identity attribute is a developer contract
 *   violation and throws LogicException. This cannot be triggered by client
 *   input: request attributes are server-side state.
 * - This middleware never mutates request attributes; it is a read-only
 *   consumer of authentication state.
 */
final class RequireAuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * The Request attribute key under which authentication exposes the
     * authenticated identity. Formally documents the authentication ->
     * authorization seam.
     */
    public const IDENTITY_ATTRIBUTE = 'identity';

    /**
     * @param AuthorizerInterface $authorizer The application-provided
     *                                        authorization decision maker.
     * @param string              $ability    Opaque ability name forwarded
     *                                        verbatim to the authorizer
     *                                        ('' = overall route access).
     */
    public function __construct(
        private readonly AuthorizerInterface $authorizer,
        private readonly string $ability = '',
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $identity = null;

        if ($request->hasAttribute(self::IDENTITY_ATTRIBUTE)) {
            $attribute = $request->getAttribute(self::IDENTITY_ATTRIBUTE);

            if (!$attribute instanceof IdentityInterface) {
                throw new LogicException(sprintf(
                    'Request attribute "%s" must implement %s, got %s.',
                    self::IDENTITY_ATTRIBUTE,
                    IdentityInterface::class,
                    get_debug_type($attribute),
                ));
            }

            $identity = $attribute;
        }

        if (!$this->authorizer->authorize($identity, $this->ability)) {
            // Denial short-circuits: the handler (and any inner middleware)
            // never executes.
            return new Response(
                '{"error":"Forbidden"}',
                403,
                [
                    'Content-Type' => 'application/json',
                ]
            );
        }

        return $next($request);
    }
}
