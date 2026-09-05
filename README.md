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

$app = new Application(__DIR__);
$app->boot();
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
