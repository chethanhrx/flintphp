# FlintPHP

A modern PHP framework for APIs and services.

**Explicit composition. No facades. No global state. No automatic discovery.**

| | |
|---|---|
| **Version** | `1.1.0` (see [CHANGELOG](CHANGELOG.md)) |
| **PHP** | 8.2+ |
| **Package** | [`flintphp/framework`](https://packagist.org/packages/flintphp/framework) |
| **Skeleton** | [`flintphp/skeleton`](https://packagist.org/packages/flintphp/skeleton) |
| **License** | MIT |

---

## Install

**New app (recommended)**

```bash
composer create-project flintphp/skeleton my-app
cd my-app
php -S localhost:8000 -t public/
```

**Into an existing project**

```bash
composer require flintphp/framework
```

If Packagist is still on an older tag, use the branch:

```bash
composer require flintphp/framework:dev-main
```

---

## First API in 30 seconds

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Exception\JsonExceptionHandler;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Observability\Contract\LoggerInterface;

$app = new Application(__DIR__);

// JSON errors + logging (logger is already registered by Application)
$logger = $app->container()->get(LoggerInterface::class);
$app->setExceptionHandler(new JsonExceptionHandler($logger));

$app->router()->get('/hello', function (Request $request): Response {
    return Response::json(['message' => 'Hello, FlintPHP']);
});

$app->router()->get('/users/{id}', function (Request $request, int $id): Response {
    return Response::json(['id' => $id]);
});

$response = $app->kernel()->handleGlobals();
$response->send();
```

Notes:

- Handlers must return a `Response`.
- Use **`[Controller::class, 'method']`** or a Closure. **`Controller@method` is not supported.**
- Typed route params that fail to cast (e.g. `/users/abc` with `int $id`) return **404**.
- Unexpected exceptions are logged (JSON lines via `error_log`) and return a generic 500.

- Prefer `Kernel::handleGlobals()` in a front controller. It keeps request-construction failures (including oversized bodies) inside the configured exception handler. `Request::fromGlobals()` can still be used directly when the application intentionally manages ingress exceptions itself.

---

## Controllers and DI

```php
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

final class UserController
{
    public function show(Request $request, int $id): Response
    {
        return Response::json(['id' => $id]);
    }

    public function store(Request $request): Response
    {
        $data = $request->json(); // throws HttpException 400 if invalid JSON

        return Response::json(['received' => $data], 201);
    }
}

$app->router()->get('/users/{id}', [UserController::class, 'show']);
$app->router()->post('/users', [UserController::class, 'store']);
```

The container resolves the controller and injects constructor dependencies automatically when they are bound or concrete classes.

---

## JSON body and errors

```php
// Decode body — empty body → []; invalid JSON / non-array → HTTP 400
$payload = $request->json();

// Success
return Response::json(['ok' => true], 200);

// Consistent error shape
return Response::error('Not Found', 404, 'not_found');
// {"error":{"message":"Not Found","status":404,"code":"not_found"}}
```

Kernel 404 / 405 responses also use this JSON error shape.

---

## Middleware

Register middleware **at route registration time**. There is no `withMiddleware()` on the route object (that pattern was removed because it silently skipped middleware).

```php
use FlintPHP\Framework\Middleware\MiddlewareInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->header('Authorization') === null) {
            return Response::error('Unauthorized', 401, 'unauthorized');
        }

        return $next($request);
    }
}

// Global
$app->container()->singleton(AuthMiddleware::class, fn () => new AuthMiddleware());
$app->addMiddleware(AuthMiddleware::class);

// Route-scoped
$app->router()->delete(
    '/users/{id}',
    [UserController::class, 'destroy'],
    middleware: [AuthMiddleware::class],
);
```

Order: global middleware (outer) → route middleware → handler.

---

## Validation

Validation is explicit. Nothing auto-validates the request.

```php
use FlintPHP\Framework\Validation\ValidationBootstrapper;
use FlintPHP\Framework\Validation\Validator;

$app->bootstrapWith([new ValidationBootstrapper()]);

// In a controller (Validator is constructor-injected after bootstrap)
$result = $validator->validate($request->json(), [
    'email' => ['required', 'string', 'email'],
    'name'  => ['required', 'string', 'min:1', 'max:100'],
]);

if (!$result->isValid()) {
    return Response::json([
        'error' => [
            'message' => 'Validation failed',
            'status' => 422,
            'code' => 'validation_failed',
            'details' => $result->errors(),
        ],
    ], 422);
}

$clean = $result->validated();
```

Built-in rules: `required`, `string`, `integer`, `email`, `min:N`, `max:N`, `in:a,b,c`.

---

## Authentication (Bearer)

```php
use FlintPHP\Framework\Authentication\AuthenticatorInterface;
use FlintPHP\Framework\Authentication\BearerTokenAuthenticator;
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Authentication\UserProviderInterface;

// Bind your UserProviderInterface implementation, then:
$app->container()->singleton(
    AuthenticatorInterface::class,
    fn ($c) => new BearerTokenAuthenticator($c->get(UserProviderInterface::class))
);

$app->container()->singleton(
    RequireAuthenticationMiddleware::class,
    fn ($c) => new RequireAuthenticationMiddleware($c->get(AuthenticatorInterface::class))
);

$app->router()->get(
    '/me',
    [ProfileController::class, 'show'],
    middleware: [RequireAuthenticationMiddleware::class],
);
```

Send: `Authorization: Bearer <token>`.

### JWT Authentication (HMAC SHA-256)

For stateless tokens, use `JwtAuthenticator` backed by `HmacJwt`:

```php
use FlintPHP\Framework\Authentication\Jwt\JwtAuthenticator;

$jwtAuth = new JwtAuthenticator(
    secret: $env['JWT_SECRET'],
    userFactory: fn (array $payload) => new User($payload['sub'], $payload['email'] ?? null),
    leewaySeconds: 30,             // Optional clock-skew tolerance (default: 0)
    expectedIssuer: 'https://api.myapp.com', // Optional iss validation
    expectedAudience: 'myapp-clients',       // Optional aud validation
);
```

**Security properties:**
- **Algorithm enforcement:** Strictly HS256. Rejects `none`, RS256, and algorithm confusion attacks.
- **Required Expiration:** The `exp` claim is strictly required and must be numeric. Tokens without `exp` fail closed.
- **Strict Boundary Check:** Tokens are invalid once `$now >= $exp + $leeway`.
- **Claim validation:** Validates `nbf` (not before), `iat` (issued at), `iss` (issuer), and `aud` (audience).
- **Bounds & Format:** Rejects tokens over 8,192 bytes, non-Base64URL characters, and malformed JSON payloads.

---

## Rate Limiting & Cache

FlintPHP provides fixed-window rate limiting. Concurrency guarantees depend on the selected cache backend and its deployment topology:

```php
use FlintPHP\Framework\Cache\FileCache;
use FlintPHP\Framework\Http\Middleware\RateLimitMiddleware;

$cache = new FileCache(__DIR__ . '/storage/cache');

// 60 requests per 60 seconds per client IP
$rateLimiter = new RateLimitMiddleware(
    cache: $cache,
    maxAttempts: 60,
    windowSeconds: 60,
    keyResolver: null,             // Defaults to $request->clientIp() ?? 'unknown'
    failClosed: true,              // Returns 429 if cache backend fails (avoids leaking errors)
);

$app->addMiddleware($rateLimiter);
```

### Concurrency Guarantees:
- **`AtomicCounterInterface`:** The interface requires an atomic increment operation with TTL initialization. Implementations must honour that contract; merely implementing the interface does not make a backend distributed.
- **`FileCache`:** Uses advisory file locks and is suitable for cooperating processes sharing a supported local filesystem. Do not assume correctness on arbitrary network filesystems or across hosts.
- **`ArrayCache`:** In-memory only. Separate PHP-FPM workers and separate hosts have separate counters; it is not a shared production rate-limit store.
- **Non-atomic drivers:** Rejected by default. An explicitly enabled compatibility fallback uses read-modify-write and is race-prone under concurrent requests; do not use it where the limit is a security boundary.
- **Multi-host deployments:** Use a shared backend with verified atomic operations. FlintPHP does not currently ship a distributed cache backend.
- **Reverse Proxy Protection:** `clientIp()` respects `TrustedProxyConfiguration`. Spoofed `X-Forwarded-For` headers from untrusted clients are strictly ignored.

---

## Database

```php
use FlintPHP\Framework\Database\ConnectionFactory;
use FlintPHP\Framework\Database\DatabaseBootstrapper;

// Standalone
$db = ConnectionFactory::make([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/storage/app.sqlite',
]);

$db->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);
$rows = $db->fetchAll('SELECT * FROM users');
$row  = $db->fetch('SELECT * FROM users WHERE id = ?', [1]);

$db->transaction(function () use ($db) {
    $db->execute('UPDATE users SET active = 1');
});

// Via Application (reads config database.*)
$app->bootstrapWith([new DatabaseBootstrapper()]);
$db = $app->container()->get(\FlintPHP\Framework\Database\ConnectionInterface::class);
```

A small data-mapper ORM exists (`OrmManager`, models). Prefer the connection API when you want full control of SQL.

---

## Config and `.env`

```php
use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Config\Env;
use FlintPHP\Framework\Config\PhpFileLoader;

// config/app.php returns an array; loaders never invent values
$config = new ConfigRepository(
    PhpFileLoader::loadDirectory(__DIR__ . '/config')
);

$app = new Application(__DIR__, $config);

// Optional: load .env yourself and merge into config or getenv()
$env = Env::load(__DIR__ . '/.env');
```

---

## Logging

By default, `Application` registers an `ErrorLogLogger` that writes **JSON lines** through PHP `error_log()`.

- Unexpected exceptions → **error** (class, message, file, line, trace, method, path)
- HTTP 4xx → **info** (no stack)
- Headers and bodies are **never** logged

```php
use FlintPHP\Framework\Http\Exception\JsonExceptionHandler;
use FlintPHP\Framework\Observability\Contract\LoggerInterface;

$logger = $app->container()->get(LoggerInterface::class);
$app->setExceptionHandler(new JsonExceptionHandler($logger));
```

Swap the binding for Monolog or any adapter that implements `LoggerInterface`.

---

## CLI

```bash
php vendor/bin/flint list
php vendor/bin/flint help
```

Or, in the skeleton: `php bin/flint list`.

---

## Design rules

| Do | Don't |
|----|--------|
| Register routes and middleware explicitly | Expect facades or auto-discovery |
| Use `[Class::class, 'method']` | Use `Class@method` strings |
| Pass `middleware:` when registering the route | Chain a post-hoc middleware mutator |
| Call `$request->json()` / validate yourself | Expect magic request binding |
| Treat all input as untrusted | Trust headers or path tricks |

**Path rules:** `Request::path()` strips `?` and `#`, requires a leading `/`, and collapses `//`. Path segments are **not** percent-decoded.

---

## WebSocket Origin Validation

For browser-facing WebSocket endpoints, configure an explicit Origin allowlist. Origin checks help prevent hostile websites from opening authenticated WebSocket connections from a visitor's browser; they do not replace authentication or authorization.

```php
use FlintPHP\\Framework\\WebSocket\\Handshake\\HandshakeValidator;
use FlintPHP\\Framework\\WebSocket\\Handshake\\OriginPolicy;

$validator = new HandshakeValidator(
    new OriginPolicy([
        'https://app.example.com',
        'https://admin.example.com',
    ]),
);
```

Pass the configured validator to the WebSocket handshake response builder. The policy matches exact HTTP(S) origins by scheme, host, and effective port; paths, credentials, malformed hostnames, and invalid ports are rejected. Missing Origin is rejected by default when a policy is configured. Set `allowMissingOrigin: true` only for clients that intentionally omit Origin and authenticate those clients independently. If no policy is supplied, the validator preserves legacy behavior and does not enforce Origin; configure one for browser-accessible endpoints.

---

## Limitations / Deferred Features

FlintPHP is intentionally explicit and does not currently provide built-in session management, CSRF protection infrastructure, or a distributed cache backend. Browser applications must integrate and configure appropriate session and CSRF protections. Multi-host deployments must provide shared infrastructure and validate its operational guarantees. The framework's presence of middleware or authentication primitives does not replace application-specific threat modelling, authorization policy review, or deployment hardening.

## Testing

```bash
composer test
```

PHPUnit helpers live under the framework test suite. Application tests typically build an `Application`, register routes, and call `$app->kernel()->handle($request)`.

---

## Security

See [SECURITY.md](SECURITY.md). Report vulnerabilities privately; do not open public issues for undisclosed security bugs.

---

## Links

- Framework: https://github.com/chethanhrx/flintphp  
- Skeleton: https://github.com/chethanhrx/flintphp-skeleton  
- Packagist: https://packagist.org/packages/flintphp/framework  
- Changelog: [CHANGELOG.md](CHANGELOG.md)  
