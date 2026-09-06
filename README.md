# FlintPHP

A fast, secure, modern PHP framework for building production-ready APIs and web applications.

> ⚠️ **Development Status**: FlintPHP is under active development and is not yet ready for production use.

## Requirements

- PHP 8.2 or higher
- Composer 2.x

## Installation

```bash
composer require flintphp/framework
```

## Quick Start

### Application Bootstrap

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

$app = new Application(__DIR__);

// Application explicitly owns the HTTP runtime components
$app->router()->get('/hello', function (Request $request): Response {
    return new Response('Hello, FlintPHP!');
});

$request = Request::fromGlobals();

// The Kernel handles the request using the application's composed routing and middleware
$response = $app->kernel()->handle($request);
```

### Routing

FlintPHP provides a fast, predictable router that matches paths to handlers. It supports static and dynamic routes.

```php
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Routing\Router;

$router = new Router();

// Static routes
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');

// Dynamic parameterized routes
$router->get('/users/{id}', 'UserController@show');
$router->delete('/users/{userId}/posts/{postId}', 'PostController@destroy');

// Matching an incoming request
$request = Request::fromGlobals();
$result = $router->match($request);

if ($result->isFound()) {
    $handler = $result->handler();
    $params = $result->parameters(); // e.g. ['id' => '42']

    // The Kernel will execute the handler...
} elseif ($result->isMethodNotAllowed()) {
    $allowed = $result->allowedMethods();
    // Return 405 Method Not Allowed
} else {
    // Return 404 Not Found
}
```

*Note: The Router does not execute handlers or controllers itself. It strictly matches the path to the registered route, allowing the Kernel to manage execution and dependency injection.*

### Middleware

FlintPHP uses a standard "onion" pipeline architecture for middleware. Middleware wraps the final request handler.

```php
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Middleware\MiddlewareStack;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (!$request->header('Authorization')) {
            return new Response('Unauthorized', 401);
        }

        // Pass to the next layer
        $response = $next($request);

        // Optionally modify the response on the way out
        return $response->withHeader('X-Processed-By', 'AuthMiddleware');
    }
}

$stack = new MiddlewareStack([
    new AuthMiddleware(),
]);

$response = $stack->handle($request, function (Request $req): Response {
    return new Response('Hello World!');
});
```

#### Route-Scoped Middleware

In addition to the global pipeline, middleware can be attached to individual routes. Scoped middleware is registered as class-name strings and resolved lazily through the Application's Container at dispatch time (singleton-friendly). Routes without scoped middleware follow the exact same dispatch path as before.

```php
$app->router()->get('/api/secure', ProfileController::class . '@show', middleware: [
    RequireAuthenticationMiddleware::class,
]);

$app->router()->get('/api/open', fn () => new Response('Public'));
```

Execution order: global middleware (outermost) → route-scoped middleware (innermost) → handler. Scoped middleware runs inside the Kernel's exception boundary, so a `Throwable` thrown by scoped middleware is converted by the `ExceptionHandler` exactly like a handler exception. Attributes set by scoped middleware (e.g. the authenticated identity) flow into the handler through the immutable Request.

### Kernel

The Kernel is the orchestrator that bridges the MiddlewareStack, Router, and HTTP Request together.

```php
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\Router;
use FlintPHP\Framework\Container\Container;
use FlintPHP\Framework\Routing\HandlerInvoker;

$container = new Container();

$router = new Router();
$router->get('/users/{id}', [UserController::class, 'show']);

$middlewareStack = new MiddlewareStack([
    // new SecurityHeadersMiddleware(),
]);

$invoker = new HandlerInvoker($container);
$kernel = new Kernel($router, $middlewareStack, $invoker);

$request = Request::fromGlobals();
$response = $kernel->handle($request);

$response->send();
```

### HTTP Request & Response

```php
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

// Request data access
$request = Request::fromGlobals();
$method = $request->method();       // 'GET'
$path   = $request->path();         // '/users'
$page   = $request->query('page');  // '2' or null
$auth   = $request->header('Authorization');

// Response building (Immutable)
$response = (new Response())
    ->withStatus(200)
    ->withHeader('Content-Type', 'text/html')
    ->withBody('<h1>Hello</h1>');

$response->send();

// JSON API response
$jsonResponse = Response::json([
    'message' => 'User created',
], status: 201);
```

### Dependency Injection Container

FlintPHP includes a PSR-11 compliant Dependency Injection Container with automatic reflection-based autowiring.

```php
use FlintPHP\Framework\Container\Container;

$container = new Container();

// 1. Explicit value binding
$container->set('config.debug', true);

// 2. Closure factories (transient)
$container->set(Database::class, function ($c) {
    return new Database($c->get('config.db'));
});

// 3. Singletons (cached after first resolution)
$container->singleton(LoggerInterface::class, function () {
    return new FileLogger('/tmp/app.log');
});

// 4. Aliases (interfaces to concretes)
$container->bind(CacheInterface::class, RedisCache::class);

// 5. Automatic Constructor Resolution (Autowiring)
// The container will automatically inspect MyService's constructor
// and recursively resolve its typed dependencies.
$service = $container->get(MyService::class);
```

### Validation

FlintPHP provides a standalone, framework-agnostic Validation component that handles untrusted input without being coupled to the HTTP Request.

```php
use FlintPHP\Framework\Validation\Validator;

$validator = new Validator();

$data = [
    'email' => 'user@example.com',
    'age'   => '25',
];

$result = $validator->validate($data, [
    'email' => ['required', 'string', 'email'],
    'age'   => ['required', 'integer', 'min:18'],
]);

if (!$result->isValid()) {
    $errors = $result->errors();
    // ['age' => ['The age must be at least 18.']]
}

$cleanData = $result->validated();
```

Built-in rules include `required`, `string`, `integer`, `email`, `min:X`, `max:X`, and `in:A,B,C`. You can also easily create custom rules by implementing `RuleInterface`.

### Database

FlintPHP includes a clean, minimal PDO-based Database Foundation.

```php
use FlintPHP\Framework\Database\ConnectionFactory;

$connection = ConnectionFactory::make([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

// Execute statement
$connection->execute('INSERT INTO users (name, age) VALUES (?, ?)', ['Chethan', 25]);

// Fetch multiple rows
$users = $connection->fetchAll('SELECT * FROM users WHERE age > :age', ['age' => 18]);

// Fetch single row
$user = $connection->fetch('SELECT * FROM users WHERE id = ?', [1]);

// Transactions
$connection->transaction(function () use ($connection) {
    $connection->execute('UPDATE users SET active = 1');
});
```

### 9. Database Foundation (v0.9.0)
*(Internal database abstraction, PDO connection handling, query builder.)*

## 10. Lightweight Data Mapper ORM (v0.10.0)
*(See ORM Foundation section.)*

## 11. Authentication (v0.11.0)
*(Stateless Bearer token authentication, HTTP password hashing, flexible UserProvider.)*

## 12. Security Foundation (v0.12.0)
*(Strict HTTP Header validation, Trusted Proxy support, secure headers middleware.)*

## 13. Cache Foundation (v0.13.0)
*(PSR-16 interface, secure JSON-serialized FileCache avoiding deserialization exploits, and in-memory ArrayCache.)*

## 14. Queue Foundation (v0.14.0)
Provides a lightweight, minimal abstraction for background job processing without relying on hidden magic or persistence.

*   `JobInterface`: Represents a queued task with a `handle()` method.
*   `QueueInterface`: Provides `push()`, `pop()`, `size()`, and `clear()`.
*   `ArrayQueue`: A strict, memory-only FIFO implementation providing isolated, non-persistent queues.
*   `Worker`: Safely processes jobs one at a time via `runOnce()`.

> **Limitations:** v0.14.0 establishes the interface contract. It is strictly in-memory. Persistent queues (Redis/database), durable delivery, retry semantics, and delayed jobs are explicitly out-of-scope for this foundation phase and deferred to future versions. Exceptions thrown during `handle()` will propagate naturally and the job is not automatically requeued.

## 15. Events Foundation (v0.15.0)
Provides a minimal, synchronous, deterministic event dispatcher. Events are just standard PHP objects; no base class is required.

*   `EventDispatcherInterface`: Provides `listen(string $eventClass, callable $listener)` and `dispatch(object $event)`.
*   **Exact Matching**: Listeners are registered to exact class names (`get_class($event)`). Inheritance and interface matching are explicitly excluded for predictability.
*   **Synchronous Execution**: Listeners execute sequentially. If a listener throws a `Throwable`, execution halts and the exception propagates naturally.
*   **Re-entrant Safe**: Dispatching an event from within a listener is completely safe.
*   **Listener Snapshots**: If a listener registers another listener during dispatch, the new listener won't execute during the *current* dispatch loop, preventing infinite recursion.

> **Limitations:** v0.15.0 is intentionally minimal. It does NOT provide asynchronous events, queued listeners, event sourcing, listener priorities, wildcard matching, or DI container auto-resolution.

## 16. CLI Foundation (v0.16.0)
Provides a small, dependency-free registry and dispatcher for console commands without relying on complex POSIX getopt parsing or global state.

*   `CommandInterface`: Contract for commands with `name()`, `description()`, and `execute(Input $input, OutputInterface $output): int`.
*   `ConsoleApplication`: The command registry. It matches the command name from `$argv`, routes it, and bubbles up integer exit codes cleanly. It does NOT call `exit()` directly, making it highly testable.
*   `Input`: Encapsulates `$argv` securely, providing `getCommandName()` and `getArgument(int $index)` for 0-indexed positional arguments.
*   `ConsoleOutput`: Encapsulates output streams with `writeLn()` for standard output and `writeErrorLn()` for standard error.

> **Limitations:** v0.16.0 focuses entirely on routing, execution, and exit codes. Options parsing (e.g. `--force=true`), auto-generated help menus, styling/colors, and container integration are deferred to future milestones. Any exception thrown inside a command propagates naturally and must be caught by the front controller.

## 17. Testing Foundation (v0.17.0)
Provides framework-specific test helpers designed to seamlessly integrate with PHPUnit, focusing exclusively on HTTP lifecycle testing.

*   `TestCase`: Extends `PHPUnit\Framework\TestCase`. Exposes helper methods like `get()`, `post()`, and `postJson()` to programmatically construct HTTP requests and dispatch them to the `Kernel`.
*   `TestResponse`: A fluent wrapper around the framework's HTTP `Response`, exposing strict assertions powered by PHPUnit (e.g., `assertOk()`, `assertStatus()`, `assertHeader()`, `assertJson()`).
*   **Decoupled & Secure**: Testing foundation introduces zero global state, avoids magic bindings, and mandates that tests inject their own `Kernel` setup via `createKernel()`.

> **Limitations:** v0.17.0 focuses exclusively on HTTP boundaries. Mocking components, DB refresh traits, queue fakes, or browser testing (e.g., Dusk) are intentionally deferred to future, domain-specific milestones.

## 18. OpenAPI Foundation (v0.18.0)
Provides a programmatic, typed, and dependency-free way to construct OpenAPI 3.1.x documents.

*   **Immutable Value Objects:** APIs are constructed using explicit PHP 8 `readonly` objects (`OpenApiDocument`, `PathItem`, `Operation`, `Schema`, etc.).
*   **Decoupled & Secure:** Operates entirely independently of the routing or container layers. All serialization goes through strict `json_encode` boundaries with `JSON_THROW_ON_ERROR`.
*   **Reference Safety:** Circular object graphs are prevented by representing `$ref` pointers explicitly with the `Reference` class.

> **Limitations:** v0.18.0 provides the construction and serialization foundation only. Automatic route discovery, YAML generation, and Swagger UI integration are intentionally deferred.

## 19. WebSocket Foundation (v0.19.0)
*(WebSocket protocol layer: frame parsing, message assembly, handshake validation.)*

## 20. Observability Foundation (v0.20.0)
Provides a structured, in-memory logging foundation with no hidden I/O.

*   `LogLevel`: A string-backed enum with eight PSR-style severity levels (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`).
*   `LoggerInterface`: A minimal logging contract with a typed `log()` method and eight convenience methods.
*   `Logger`: An in-memory structured logger that stores `LogRecord` objects. Performs no file, network, or database I/O. Channel names are validated against a strict grammar.
*   `NullLogger`: A no-op implementation that silently discards all log messages.
*   `LogRecord`: An immutable (`final readonly`) value object capturing level, message, context, channel, and timestamp.

> **Caller responsibility:** The logger does not inspect, sanitize, or redact context values. Callers are responsible for ensuring that sensitive data (passwords, tokens, secrets) is not passed into log context. A framework logger cannot reliably detect secrets in arbitrary key/value data.

> **Limitations:** v0.20.0 establishes the structured logging foundation only. File logging, database logging, log rotation, exporters, tracing, and OpenTelemetry integration are intentionally deferred to future versions. The current `Logger` is strictly in-memory and intended as a building block for future I/O adapters.

## 21. Metrics Foundation (v0.21.0)
Provides a minimal, deterministic, in-memory metrics foundation with no hidden I/O, no external dependencies, and no labels/tags.

*   `MetricInterface`: Common contract requiring `name(): string`.
*   `Counter`: A monotonically increasing integer counter. Starts at zero; only non-negative increments are accepted. Integer overflow is explicitly detected and rejected.
*   `Gauge`: A signed integer gauge supporting `set()`, `increment()`, and `decrement()`. Integer overflow and underflow are explicitly detected and rejected.
*   `Histogram`: Tracks observed finite floating-point values, maintaining count, sum, minimum, and maximum. NaN, +INF, and -INF are rejected. Sum overflow to non-finite is detected and rejected.
*   `MetricRegistryInterface` / `MetricRegistry`: An in-memory registry that creates, caches, and retrieves metrics by name. Repeated lookups return the same instance. Cross-type name collisions are rejected. Each registry instance is fully isolated with no static or global state.

> **Limitations:** v0.21.0 provides the in-memory metrics foundation only. Labels/tags, Prometheus/OpenTelemetry/StatsD exporters, HTTP endpoints, persistence, automatic instrumentation, and background aggregation are intentionally deferred to future versions.

## 22. Configuration Foundation (v0.22.0)
Provides a strict, immutable, nested configuration repository powered by an array without hidden I/O.

*   `ConfigRepositoryInterface` / `ConfigRepository`: A read-only repository instantiated with a configuration array.
*   **Nested Lookup (`get()` / `has()`)**: Uses dot-notation (e.g., `app.name`) to traverse nested arrays. Fully supports explicit `null`, `false`, `0`, `""`, and `[]` values.
*   **Strict Key Validation**: Keys must strictly match `\A[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*\z` and cannot exceed 128 characters.
*   **Snapshot Export (`all()`)**: Returns the complete configuration array without exposing internal mutability.
*   **DI Integration**: `ConfigRepositoryInterface` can be explicitly registered with FlintPHP's existing container and then injected through normal constructor dependency resolution. v0.22 does not automatically register a global configuration repository.

> **Limitations:** v0.22.0 provides the array-backed foundation only. `.env` parsing, environment variable loading, configuration files, YAML/JSON loaders, filesystem scanning, configuration caching, secrets managers, remote configuration, runtime mutation, configuration watchers, hot reload, typed configuration objects, and configuration schema validation are explicitly deferred to future versions.

## 23. Application Bootstrap Foundation (v0.23.0)
Establishes the `Application` class as the minimal composition root for the framework without introducing global state.

*   **Container Ownership**: The Application creates and owns its explicit DI container, which is securely instance-scoped. There is no external container replacement API.
*   **Configuration Integration**: Configuration can be explicitly supplied via the constructor, and the repository is securely and deterministically registered into the container. Normal constructor dependency resolution works identically.
*   **Idempotent Boot**: The application `boot()` method is fully idempotent and does not replace, mutate, or duplicate existing configuration or container registrations.
*   **Strict Isolation**: There is no global or static Application instance. No `config()` global helper. Multiple Applications can run concurrently with distinct DI containers and configurations without leakage.

> **Limitations:** v0.23.0 strictly defines the bootstrap lifecycle. It explicitly does **not** implement automatic configuration loading from `.env`, environment variables, YAML, JSON, or PHP configuration files. There is no automatic filesystem scanning, remote configuration, configuration caching, or runtime mutation. Automatic wiring of components like the Database, Router, or Logger is intentionally deferred.

## 24. Request Attributes Foundation (v0.24.0)
Provides a primitive for attaching request-scoped metadata to an immutable Request.

*   **Request-Scoped**: Attributes belong to a specific Request instance. There is no global context, no static state, and no `Context` singleton.
*   **Immutable API**: The Request remains immutable. `withAttribute(string $key, mixed $value)` and `withoutAttribute(string $key)` return a new Request instance, preserving all existing properties.
*   **Arbitrary Keys and Values**: Supports arbitrary string keys (case-sensitive) and opaque mixed values. Explicit `null` values are fully supported and distinguishable from missing keys.
*   **No Magic Integration**: Does not automatically populate user, route, or request ID data.

> **Limitations:** v0.24.0 provides the primitive only. The framework does not automatically extract tracing information, populate the authenticated user into attributes (unless done explicitly by existing middleware), or provide specialized `ContextAttribute` wrappers. Arbitrary user objects are stored by reference (standard PHP behavior) and are not deeply cloned when creating a new request.

## 25. Application HTTP Runtime Foundation (v0.25.0)
Application is now the explicit HTTP composition root for the framework.

*   **Explicit Composition**: The Application explicitly constructs, owns, and exposes the HTTP runtime components (`Router`, `MiddlewareStack`, and `Kernel`).
*   **Container Integration**: The Application's owned runtime components are automatically registered into the application's single `Container` instance.
*   **Complete Isolation**: Different `Application` instances maintain completely independent routes, middleware, kernels, and configurations without state leakage.
*   **No Global State**: FlintPHP avoids `static $app` facades or global functions entirely, preferring dependency injection and explicit component assembly.

> **Limitations:** v0.25.0 simply composes the foundation. It does not provide lazy loading, route caching, middleware priority systems, attribute-based routing, automatic controller discovery, or implicit file-system configuration loading. The Application's `boot()` method remains intentionally minimal at this stage.

## 26. Exception Handling Foundation (v0.26.0)
Introduces a minimal framework-level HTTP exception handling boundary.

*   **Kernel-Level Exception Boundary**: Exceptions thrown by middleware or routed handlers are caught by the Kernel and passed to the exception handler to generate an HTTP Response.
*   **Generic Default Response**: The default exception handler returns a generic 500 Internal Server Error response. It intentionally hides exception details, messages, and stack traces to prevent leaking sensitive information to clients.
*   **ExceptionHandlerInterface**: Defines a strict deterministic contract `handle(Throwable $e, Request $req): Response`.
*   **Application Composition**: The Application explicitly constructs, owns, and exposes the exception handler, registering it into the Container for deterministic resolution.
*   **Handler Failure Safety**: If the exception handler itself throws, the new exception propagates normally to prevent infinite recursive error loops.

> **Limitations:** v0.26.0 is intentionally minimal. It defers debug rendering, automatic logging, metrics recording, and event dispatching. It focuses strictly on bounding the HTTP request lifecycle securely. It does NOT register global PHP error/exception handlers.

## 27. HTTP Error Response Foundation (v0.27.0)
Implements a minimal HTTP error-response foundation on top of the v0.26 exception boundary.

*   **HttpException**: A minimal framework exception (`FlintPHP\Framework\Http\Exception\HttpException`) representing a controlled HTTP error. It accepts an HTTP status code (100–599) and an optional public message.
*   **Controlled Responses**: Throwing an `HttpException` generates an HTTP response with the specified status and public message, safely exposing only intentional application-level information to the client.
*   **Generic Fallback**: Throwing any other `Throwable` continues to generate a generic 500 Internal Server Error response, strictly hiding sensitive exception details (messages, stack traces, file paths, etc.).

> **Limitations:** v0.27.0 focuses exclusively on distinguishing controlled HTTP errors from unexpected runtime failures. It does not include a large exception hierarchy (e.g., `HttpNotFoundException`), JSON error formatting, or global PHP error handlers.

## 28. Single-Phase Application Bootstrapper Foundation (v0.28.0)
Introduces a minimal, explicit application composition mechanism.

*   **BootstrapperInterface**: A strict single-method interface (`bootstrap(Application $app): void`) that allows developers to encapsulate Application configuration and Container bindings cleanly.
*   **Sequential Execution**: The new `Application::bootstrapWith(array $bootstrappers)` method executes an array of bootstrappers in strict, exact procedural order.
*   **No Magic**: Completely avoids traditional 2-phase Service Provider architectures, file system discovery, magic string resolution, and deferred lifecycles. Bootstrappers execute exactly when provided, in the order provided, passing the exact same `Application` instance.

> **Limitations:** v0.28.0 only provides the composition primitive. It does not include native bootstrappers for the framework's optional components (e.g., Database, Cache, Auth). These must currently be implemented in user-land.

## 29. Database Integration (v0.29.0)
Integrates the Database foundation via the new `DatabaseBootstrapper`.

*   **Explicit Registration**: Must be manually added via `$app->bootstrapWith([new DatabaseBootstrapper()])`.
*   **Lazy Resolution**: Registers a closure in the container. No database connection, network traffic, or PDO instantiation occurs until the `ConnectionInterface` is actually resolved from the Container.
*   **Abstraction Binding**: Binds strictly to `ConnectionInterface::class` as a singleton. It avoids exposing implementation details like `PdoConnection` or `ConnectionFactory`.
*   **Configuration Contract**: Safely extracts the `database` array from the `ConfigRepositoryInterface` exactly when the closure is executed.

> **Limitations:** v0.29.0 does not include connection pooling, active record patterns, database migrations, or automatic setup. It focuses strictly on wiring the existing lazy database primitive cleanly into the Application Container.

## 30. ORM Integration (v0.30.0)
Integrates the ORM component into the Application via the new `OrmBootstrapper`.

*   **Explicit Registration**: Must be manually composed via `$app->bootstrapWith([new OrmBootstrapper()])`.
*   **Lazy Singleton**: Registers `OrmManager::class` as an Application-scoped lazy singleton. The ORM manager is only instantiated when it is resolved from the Container.
*   **Database Dependency**: `OrmBootstrapper` requests `ConnectionInterface::class` from the container, ensuring it natively inherits the lazy PDO connection logic established by `DatabaseBootstrapper`.
*   **No Magic**: The ORM remains strictly Data Mapper based. Models are not automatically discovered, Query Builders remain transient, and there are no global DB/ORM facades.

> **Limitations:** v0.30.0 is an integration release only. It explicitly does not introduce connection pooling, ORM-managed migrations, active record models, relationships, or automatic model registration.

## 31. Authentication Composition (v0.31.0)
Integrates the Authentication component into the Application and introduces route-scoped middleware.

*   **Route-Scoped Middleware**: `Router::add()` and every verb method (`get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`, `options()`) accept an optional trailing `array $middleware` of class-name strings. Existing 3-argument calls keep working — the change is purely additive. `Route::middleware()` exposes the list and `Route::withMiddleware()` derives a new immutable Route.
*   **Scoped Dispatch**: Scoped middleware resolves through the Container (singleton-friendly) and executes inside the global pipeline and inside the Kernel's exception boundary. Routes without scoped middleware take the identical dispatch path as before, with zero added allocations.
*   **AuthenticationBootstrapper**: Registers `AuthenticatorInterface` as a lazy singleton defaulting to `BearerTokenAuthenticator`, and `RequireAuthenticationMiddleware` for route-scoped use. Add it via `$app->bootstrapWith([new AuthenticationBootstrapper()])`.
*   **Explicit UserProvider Contract**: The developer MUST explicitly bind their own `UserProviderInterface` implementation. Authentication is intentionally NOT coupled to the ORM, no User model and no universal users table is assumed, and there is no global `Auth::user()` state. If no provider is bound, the first authentication attempt fails with a Container `NotFoundException` — a deliberate fail-loud signal. To use a fully custom authenticator, rebind `AuthenticatorInterface` after bootstrapping; the default is lazy and never instantiated unless resolved.

> **Limitations:** v0.31.0 provides authentication composition only. Authorization (policies/permissions) is deferred to the next milestone. Sessions, CSRF protection, and token issuance/revocation flows remain out of scope.

## 32. Authorization Foundation (v0.32.0)
Adds a minimal, explicit authorization primitive, strictly separated from Authentication:

*   **Authentication** answers *"who is this request associated with?"* and is handled by the Authentication component.
*   **Authorization** answers *"is this identity allowed to perform this operation?"* and is implemented by the application.

The two share exactly one seam: the authenticated identity attached to the immutable Request attribute `'identity'`. Authorization never mutates authentication state, and neither component depends on the other's middleware or bootstrapper.

*   **AuthorizerInterface**: a single boolean decision contract — `authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool`. The ability string is an opaque, developer-defined vocabulary (`''` conventionally means overall route access); the framework never interprets it. `$resource` supports fine-grained, controller-side checks.
*   **No default implementation**: applications MUST explicitly bind their own `AuthorizerInterface` (policy logic is application-specific by design). If none is bound, the first protected request fails loudly with a container `NotFoundException` — fail-closed, never fail-open. There is no deny-all fallback because a silent default would mask misconfiguration.
*   **RequireAuthorizationMiddleware**: on allow, passes the request through unchanged; on deny, short-circuits with a fixed `403` JSON response (`{"error":"Forbidden"}`) containing no internal details. It runs inside the global pipeline and the Kernel's exception boundary: an authorizer exception propagates to the exception handler (generic 500) and the request never proceeds — exceptions are never converted into denials, and failures never grant access.
*   **AuthorizationBootstrapper**: registers `RequireAuthorizationMiddleware` as a lazy container singleton. Compose via `$app->bootstrapWith([new AuthorizationBootstrapper()])`.

```php
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Authorization\AuthorizationBootstrapper;
use FlintPHP\Framework\Authorization\AuthorizerInterface;
use FlintPHP\Framework\Authorization\Middleware\RequireAuthorizationMiddleware;

// 1. Bind YOUR authorization policy (the framework provides none)
$app->container()->singleton(AuthorizerInterface::class, function () {
    return new MyPolicyAuthorizer(); // your implementation
});

$app->bootstrapWith([new AuthorizationBootstrapper()]);

// 2. Protect routes; authentication first so authorization sees the identity
$app->router()->get('/admin', AdminController::class . '@index', middleware: [
    RequireAuthenticationMiddleware::class,
    RequireAuthorizationMiddleware::class,
]);
```

For per-route abilities, register a preconfigured middleware under a custom container id:

```php
$app->container()->set('auth.ability:posts.manage', function ($c) {
    return new RequireAuthorizationMiddleware($c->get(AuthorizerInterface::class), 'posts.manage');
});

$app->router()->post('/posts', $handler, middleware: ['auth.ability:posts.manage']);
```

Controllers can perform fine-grained checks by injecting `AuthorizerInterface` directly:

```php
public function update(Request $request, int $id): Response
{
    $post = /* ... */;
    $identity = $request->getAttribute('identity');

    if (!$this->authorizer->authorize($identity, 'update', $post)) {
        throw new HttpException(403, 'Forbidden');
    }

    // ...
}
```

> **Security guarantees:** enforcement is fail-closed — denial, authorizer exception, unbound implementation, and identity contract violations all end with the request *not* proceeding. Request attributes are server-side state; clients cannot spoof the identity. Authorization never interprets ability strings: never derive them from request input.
>
> **Limitations:** v0.32.0 provides the authorization primitive only. Roles/permissions models, database-backed ACLs, policy classes with discovery, gates/facades, and decision events are intentionally deferred. The correctness of authorization logic is entirely the application's responsibility.

## Installation

### ORM Foundation

FlintPHP includes a lightweight Data Mapper ORM that strictly separates persistence from data. It avoids magic properties in favor of IDE-friendly typed properties and rejects global database state by requiring instance-based API usage.

```php
use FlintPHP\Framework\Orm\Model;
use FlintPHP\Framework\Orm\OrmManager;

// 1. Define a strictly typed model
class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email'];

    public int $id;
    public string $name;
    public string $email;
}

// 2. Instantiate the OrmManager with a ConnectionInterface
$orm = new OrmManager($connection);

// 3. Find and update
$user = $orm->find(User::class, 1);
$user->name = 'Jane Doe';
$orm->save($user);

// 4. Query builder
$activeUsers = $orm->query(User::class)
    ->where('active', 1)
    ->get();

// 5. Mass assignment securely limits payload using $fillable
$newUser = new User();
$orm->fill($newUser, $_POST);
$orm->save($newUser);
```

### Model Contract

When defining FlintPHP ORM models, the following architectural rules apply:

1. **Only public, non-static properties** are treated as ORM attributes and are persisted/hydrated.
2. **Protected and private properties** are internal to the object and are never treated as ORM attributes.
3. **Static properties** are never persisted or hydrated.
4. **Hydration bypasses constructors**. When `$orm->find()` fetches a record, the model is instantiated without calling its constructor to prevent unintended side effects on existing entities.
5. **Transient properties** (public properties initialized at runtime that do not correspond to database columns) should be avoided, as the ORM will attempt to persist them, resulting in a database exception. All initialized public properties are treated as persistable attributes.
6. Model attributes are expected to match the database columns used by the ORM.

### Authentication Foundation

FlintPHP v0.11 provides a strictly typed, stateless Authentication Foundation designed around Bearer Tokens, completely avoiding global state such as `Auth::user()`.

The framework intercepts authentication via the `RequireAuthenticationMiddleware`, verifies credentials, and securely passes the authenticated `IdentityInterface` down the stack as an immutable Request attribute.

```php
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;

// 1. In a Controller or downstream Middleware, retrieve the isolated Identity
class ProfileController
{
    public function show(Request $request): Response
    {
        $identity = $request->getAttribute('identity');

        return new Response(200, [], 'Hello user: ' . $identity->getIdentifier());
    }
}
```

Authentication errors (such as missing or invalid Bearer tokens) are caught securely by the middleware, which immediately short-circuits the request and returns a standard `401 Unauthorized` HTTP response containing the `WWW-Authenticate: Bearer` header.

## Philosophy

FlintPHP is built around these principles:

- **Fast by default** — Performance is a design consideration, not an afterthought.
- **Secure by default** — Security-first architecture with safe defaults.
- **Modern PHP** — Leverages PHP 8.2+ features: strict types, enums, readonly properties, and more.
- **Clean architecture** — Explicit behavior over magic. Composition over inheritance.
- **Modular** — Use only what you need. No monolithic god objects.
- **Tested** — Every meaningful feature has automated tests.

## Current Limitations

- No wildcard/catch-all routes
- No optional route parameters
- Uploaded file parsing is not yet implemented
- Response streaming is not yet supported

## Development

### Running Tests

```bash
composer test
```

## License

FlintPHP is open-source software licensed under the [MIT License](LICENSE).
