<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Integration;

use FlintPHP\Framework\Config\ConfigRepository;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\DatabaseBootstrapper;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Exception\HttpException;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use FlintPHP\Framework\Security\SecurityBootstrapper;
use FlintPHP\Framework\Validation\ValidationBootstrapper;
use FlintPHP\Framework\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests simulating a realistic mini-API request lifecycle.
 *
 * Covers gaps from Phase A audit:
 * - POST/PUT with JSON body parsing and validation
 * - Global + scoped middleware ordering end-to-end through Application
 * - Database write from POST handler with prepared statements
 * - Error response consistency across different failure paths
 * - Multiple sequential requests through one Application
 */
#[CoversNothing]
#[RequiresPhpExtension('pdo_sqlite')]
final class ApiLifecycleIntegrationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application('/tmp', new ConfigRepository([
            'database' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]));

        $this->app->bootstrapWith([
            new DatabaseBootstrapper(),
            new SecurityBootstrapper(),
            new ValidationBootstrapper(),
        ]);

        // Create a simple table for the test API
        $db = $this->app->container()->get(ConnectionInterface::class);
        $db->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT)');
    }

    // ---------------------------------------------------------------
    // POST with JSON body + validation + DB write
    // ---------------------------------------------------------------

    #[Test]
    public function post_request_with_json_body_creates_resource(): void
    {
        $this->app->router()->post('/posts', function (Request $request, Validator $v, ConnectionInterface $db): Response {
            $data = json_decode($request->body(), true) ?? [];
            $result = $v->validate($data, [
                'title' => ['required', 'string', 'max:100'],
                'body' => ['string'],
            ]);

            if (!$result->isValid()) {
                return Response::json(['errors' => $result->errors()], 422);
            }

            $validated = $result->validated();
            $db->execute(
                'INSERT INTO posts (title, body) VALUES (?, ?)',
                [$validated['title'], $validated['body'] ?? null]
            );

            $id = (int) $db->pdo()->lastInsertId();

            return Response::json(['id' => $id, 'title' => $validated['title']], 201);
        });

        // Valid POST
        $validRequest = new Request(
            method: 'POST',
            uri: '/posts',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode(['title' => 'Hello World', 'body' => 'My first post']),
        );

        $response = $this->app->kernel()->handle($validRequest);

        $this->assertSame(201, $response->status());
        $this->assertSame('application/json', $response->header('Content-Type'));

        $body = json_decode($response->body(), true);
        $this->assertSame(1, $body['id']);
        $this->assertSame('Hello World', $body['title']);

        // Security headers applied
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    #[Test]
    public function post_request_with_invalid_json_body_returns_validation_errors(): void
    {
        $this->app->router()->post('/posts', function (Request $request, Validator $v): Response {
            $data = json_decode($request->body(), true) ?? [];
            $result = $v->validate($data, [
                'title' => ['required', 'string', 'max:100'],
            ]);

            if (!$result->isValid()) {
                return Response::json(['errors' => $result->errors()], 422);
            }

            return new Response('should not reach');
        });

        // Missing title
        $invalidRequest = new Request(
            method: 'POST',
            uri: '/posts',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode(['body' => 'no title']),
        );

        $response = $this->app->kernel()->handle($invalidRequest);

        $this->assertSame(422, $response->status());
        $errors = json_decode($response->body(), true)['errors'];
        $this->assertArrayHasKey('title', $errors);
    }

    #[Test]
    public function post_request_with_empty_body_fails_validation(): void
    {
        $this->app->router()->post('/posts', function (Request $request, Validator $v): Response {
            $data = json_decode($request->body(), true) ?? [];
            $result = $v->validate($data, [
                'title' => ['required', 'string'],
            ]);

            if (!$result->isValid()) {
                return Response::json(['errors' => $result->errors()], 422);
            }

            return new Response('should not reach');
        });

        $emptyRequest = new Request(
            method: 'POST',
            uri: '/posts',
            body: '',
        );

        $response = $this->app->kernel()->handle($emptyRequest);

        $this->assertSame(422, $response->status());
    }

    // ---------------------------------------------------------------
    // Global + scoped middleware ordering end-to-end
    // ---------------------------------------------------------------

    #[Test]
    public function global_middleware_wraps_scoped_middleware_in_application(): void
    {
        $log = [];

        // Global middleware: adds X-Global header
        $globalMiddleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'global-before';
                $response = $next($request);
                $this->log[] = 'global-after';
                return $response->withHeader('X-Global', 'applied');
            }
        };

        // Scoped middleware: adds X-Scoped header
        $scopedMiddleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function process(Request $request, callable $next): Response
            {
                $this->log[] = 'scoped-before';
                $response = $next($request);
                $this->log[] = 'scoped-after';
                return $response->withHeader('X-Scoped', 'applied');
            }
        };

        $globalClass = get_class($globalMiddleware);
        $scopedClass = get_class($scopedMiddleware);

        $this->app->container()->singleton($globalClass, $globalMiddleware);
        $this->app->container()->singleton($scopedClass, $scopedMiddleware);
        $this->app->addMiddleware($globalClass);

        $this->app->router()->get('/scoped-route', function () use (&$log): Response {
            $log[] = 'handler';
            return new Response('ok');
        }, middleware: [$scopedClass]);

        $response = $this->app->kernel()->handle(new Request('GET', '/scoped-route'));

        $this->assertSame(200, $response->status());
        $this->assertSame('applied', $response->header('X-Global'));
        $this->assertSame('applied', $response->header('X-Scoped'));
        $this->assertSame([
            'global-before',
            'scoped-before',
            'handler',
            'scoped-after',
            'global-after',
        ], $log);
    }

    // ---------------------------------------------------------------
    // Multiple sequential requests through one Application
    // ---------------------------------------------------------------

    #[Test]
    public function multiple_request_lifecycle_through_one_application(): void
    {
        $this->app->router()->get('/list', function (ConnectionInterface $db): Response {
            $rows = $db->fetchAll('SELECT * FROM posts ORDER BY id ASC');
            return Response::json($rows);
        });

        $this->app->router()->post('/create', function (Request $request, Validator $v, ConnectionInterface $db): Response {
            $data = json_decode($request->body(), true) ?? [];
            $result = $v->validate($data, ['title' => ['required', 'string']]);

            if (!$result->isValid()) {
                throw new HttpException(422, 'Validation failed');
            }

            $db->execute('INSERT INTO posts (title) VALUES (?)', [$result->validated()['title']]);
            return Response::json(['id' => (int) $db->pdo()->lastInsertId()], 201);
        });

        // 1. List (empty)
        $list1 = $this->app->kernel()->handle(new Request('GET', '/list'));
        $this->assertSame(200, $list1->status());
        $this->assertSame('[]', $list1->body());

        // 2. Create
        $create = $this->app->kernel()->handle(new Request(
            method: 'POST',
            uri: '/create',
            body: json_encode(['title' => 'First Post']),
        ));
        $this->assertSame(201, $create->status());

        // 3. List (one item)
        $list2 = $this->app->kernel()->handle(new Request('GET', '/list'));
        $this->assertSame(200, $list2->status());
        $posts = json_decode($list2->body(), true);
        $this->assertCount(1, $posts);
        $this->assertSame('First Post', $posts[0]['title']);

        // 4. 404
        $notFound = $this->app->kernel()->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $notFound->status());

        // 5. 405
        $methodNotAllowed = $this->app->kernel()->handle(new Request('DELETE', '/list'));
        $this->assertSame(405, $methodNotAllowed->status());
        $this->assertSame('GET', $methodNotAllowed->header('Allow'));
    }

    // ---------------------------------------------------------------
    // Error responses are consistent
    // ---------------------------------------------------------------

    #[Test]
    public function error_responses_never_leak_internal_details(): void
    {
        $this->app->router()->get('/db-error', function (ConnectionInterface $db): Response {
            // Query a table that doesn't exist
            $db->fetchAll('SELECT * FROM nonexistent_table_xyz');
            return new Response('should not reach');
        });

        $this->app->router()->get('/uncaught', function (): Response {
            throw new \RuntimeException('secret database password: abc123');
        });

        $this->app->router()->get('/http-error', function (): Response {
            throw new HttpException(403, 'Access denied');
        });

        $dbError = $this->app->kernel()->handle(new Request('GET', '/db-error'));
        $this->assertSame(500, $dbError->status());
        $this->assertStringNotContainsString('nonexistent_table', $dbError->body());
        $this->assertStringNotContainsString('SQL', $dbError->body());

        $uncaught = $this->app->kernel()->handle(new Request('GET', '/uncaught'));
        $this->assertSame(500, $uncaught->status());
        $this->assertStringNotContainsString('abc123', $uncaught->body());
        $this->assertStringNotContainsString('RuntimeException', $uncaught->body());

        $httpError = $this->app->kernel()->handle(new Request('GET', '/http-error'));
        $this->assertSame(403, $httpError->status());
        $this->assertStringContainsString('Access denied', $httpError->body());
    }

    // ---------------------------------------------------------------
    // Prepared statements: SQL injection proof
    // ---------------------------------------------------------------

    #[Test]
    public function prepared_statements_prevent_sql_injection(): void
    {
        $this->app->router()->post('/create', function (Request $request, ConnectionInterface $db): Response {
            $data = json_decode($request->body(), true) ?? [];
            $title = $data['title'] ?? '';

            $db->execute('INSERT INTO posts (title) VALUES (?)', [$title]);

            return Response::json(['title' => $title], 201);
        });

        // Try SQL injection in title
        $malicious = "'; DROP TABLE posts; --";
        $response = $this->app->kernel()->handle(new Request(
            method: 'POST',
            uri: '/create',
            body: json_encode(['title' => $malicious]),
        ));

        $this->assertSame(201, $response->status());

        // Verify the table still exists and the injection was stored as data
        $db = $this->app->container()->get(ConnectionInterface::class);
        $row = $db->fetch('SELECT title FROM posts WHERE id = 1');
        $this->assertNotNull($row);
        $this->assertSame($malicious, $row['title']);
    }
}
