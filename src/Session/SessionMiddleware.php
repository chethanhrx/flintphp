<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Session;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;

/**
 * Loads and persists a server-side session around the downstream pipeline.
 *
 * Register explicitly and before middleware that consumes the session. Session
 * IDs are opaque 256-bit random values; client-provided IDs are accepted only
 * when they match the format generated here and exist in the configured store.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'flint.session';

    public function __construct(
        private readonly SessionStoreInterface $store,
        private readonly SessionCookieConfiguration $cookie = new SessionCookieConfiguration(),
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $candidate = $request->cookie($this->cookie->name);
        $validCandidate = is_string($candidate)
            && preg_match('/\A[a-f0-9]{64}\z/', $candidate) === 1;

        $id = $validCandidate ? $candidate : null;
        $data = $id !== null ? $this->store->read($id) : null;

        if ($id === null || $data === null) {
            $id = bin2hex(random_bytes(32));
            $data = [];
        }

        $session = new Session($id, $data);
        $response = $next($request->withAttribute(self::ATTRIBUTE, $session));

        $previousId = $session->previousId();
        if ($previousId !== null) {
            $this->store->delete($previousId);
        }

        $this->store->write($session->id(), $session->all(), time() + $this->cookie->lifetimeSeconds);

        $headers = $response->headers()->all();
        $setCookieName = null;

        foreach ($headers as $name => $values) {
            if (strtolower($name) === 'set-cookie') {
                $setCookieName = $name;
                break;
            }
        }

        $setCookieName ??= 'Set-Cookie';
        $headers[$setCookieName] = [
            ...($headers[$setCookieName] ?? []),
            $this->cookie->serialize($session->id()),
        ];

        return new Response(
            body: $response->body(),
            status: $response->status(),
            headers: $headers,
        );
    }
}
