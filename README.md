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

The database component deliberately focuses on infrastructure and does not currently include an ORM or Query Builder.

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
