# Sessions and CSRF protection

FlintPHP sessions and CSRF protection are explicit middleware features. They are
not enabled automatically, so existing applications keep their current behavior.

## 1. Configure a session store

FlintPHP includes two stores:

- `ArraySessionStore` is process-local and intended only for tests or local development.
- `FileSessionStore` persists sessions across requests and process restarts on a single host. Store its directory outside the public document root. It applies restrictive directory/file permissions and uses locks plus atomic file replacement.
- For multi-host deployments, implement `SessionStoreInterface` with a shared database or cache backend.

Example file-store configuration:

    use FlintPHP\Framework\Session\SessionCookieConfiguration;
    use FlintPHP\Framework\Session\SessionMiddleware;
    use FlintPHP\Framework\Session\Store\FileSessionStore;

    $sessionStore = new FileSessionStore(__DIR__ . '/../var/sessions');
    $sessionMiddleware = new SessionMiddleware(
        $sessionStore,
        new SessionCookieConfiguration(
            name: '__Host-flint_session',
            lifetimeSeconds: 1800,
            secure: true,
            httpOnly: true,
            sameSite: 'Lax',
        ),
    );

Register the middleware explicitly in the application's middleware pipeline.
It should run before middleware or handlers that access session state. The
default cookie uses the __Host- prefix, which requires HTTPS, Path=/, and
no Domain attribute. For plain HTTP local development, choose a non-__Host-
cookie name and set secure: false; never use that setting for a production
site.

## 2. Read and write session data

The middleware attaches a Session object to the request using
SessionMiddleware::ATTRIBUTE.

    use FlintPHP\Framework\Session\Session;
    use FlintPHP\Framework\Session\SessionMiddleware;

    $handler = static function ($request) {
        $session = $request->attribute(SessionMiddleware::ATTRIBUTE);

        if (!$session instanceof Session) {
            throw new LogicException('Session middleware is not configured.');
        }

        $session->set('cart_count', 2);
        $cartCount = $session->get('cart_count', 0);

        // Return your normal FlintPHP Response here.
    };

Session IDs are generated with random_bytes(32); client-supplied IDs that are
malformed or unknown to the store are not adopted. After login or another
privilege change, call $session->regenerate() to rotate the ID and have the
middleware delete the previous server-side record.

FileSessionStore serializes session records as JSON, so session values must be
JSON-serializable. It treats malformed JSON records as invalid sessions.
Keep the storage directory private and outside the web root. It is a single-host
file backend, not a distributed session store.

## 3. Add CSRF protection

Register CsrfMiddleware after SessionMiddleware. Obtain the token from the
same request's session and render it into your page or return it to a trusted
client. Send it back in the X-CSRF-Token header on state-changing requests.

    use FlintPHP\Framework\Session\CsrfTokenManager;
    use FlintPHP\Framework\Session\CsrfMiddleware;

    $csrfTokens = new CsrfTokenManager();
    $csrfMiddleware = new CsrfMiddleware($csrfTokens);

    // In a handler, once the session is available:
    $token = $csrfTokens->token($session);

The middleware skips GET, HEAD, and OPTIONS. Other methods require a token and
receive a 419 JSON error when it is missing or invalid. Comparison uses
hash_equals(). The current implementation accepts tokens from a configurable
header; form-body parsing is intentionally left to a future integration.

## Operational notes

- Sessions are server-side; the cookie contains only a random opaque ID.
- Keep session data serializable and avoid putting unnecessary sensitive data in it.
- Use a shared, durable store in multi-host deployments.
- Configure secure cookies only behind HTTPS and ensure proxy/TLS configuration
  reflects the actual public connection.
- CSRF middleware protects cookie-authenticated browser flows; it is not a
  replacement for authentication, authorization, output escaping, or origin policy.
- This feature is new and should be reviewed and tested in the target deployment
  before being relied on for sensitive production workflows.
