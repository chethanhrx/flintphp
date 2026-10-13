# Sessions and CSRF protection

FlintPHP sessions and CSRF protection are explicit middleware features. They are
not enabled automatically, so existing applications keep their current behavior.

## 1. Configure a session store

Implement SessionStoreInterface using a durable server-side backend for
production (for example, a database or shared cache). The bundled
ArraySessionStore is process-local and is only suitable for tests or local
development; it does not survive process restarts and is not shared between
workers.

Example configuration:

    use FlintPHP\Framework\Session\SessionCookieConfiguration;
    use FlintPHP\Framework\Session\SessionMiddleware;
    use FlintPHP\Framework\Session\Store\ArraySessionStore;

    $sessionStore = new ArraySessionStore(); // development/testing only
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
- Use a shared, durable store in multi-worker or multi-instance deployments.
- Configure secure cookies only behind HTTPS and ensure proxy/TLS configuration
  reflects the actual public connection.
- CSRF middleware protects cookie-authenticated browser flows; it is not a
  replacement for authentication, authorization, output escaping, or origin policy.
- This feature is new and should be reviewed and tested in the target deployment
  before being relied on for sensitive production workflows.
