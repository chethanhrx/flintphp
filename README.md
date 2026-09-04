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
