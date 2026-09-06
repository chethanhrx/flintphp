<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Integration;

use FlintPHP\Framework\Authentication\AuthenticationBootstrapper;
use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Authentication\UserProviderInterface;
use FlintPHP\Framework\Authorization\AuthorizationBootstrapper;
use FlintPHP\Framework\Authorization\AuthorizerInterface;
use FlintPHP\Framework\Authorization\Middleware\RequireAuthorizationMiddleware;
use FlintPHP\Framework\Database\ConnectionInterface;
use FlintPHP\Framework\Database\DatabaseBootstrapper;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Exception\HttpException;
use FlintPHP\Framework\Http\Kernel;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareStack;
use FlintPHP\Framework\Routing\HandlerInvoker;
use FlintPHP\Framework\Security\SecurityBootstrapper;
use FlintPHP\Framework\Validation\ValidationBootstrapper;
use FlintPHP\Framework\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Canonical v1 full-composition test: one Application composed exactly as the
 * documentation recommends, exercising bootstrappers, routing, scoped
 * middleware, authentication, authorization, validation, the exception
 * boundary, and error semantics end to end.
 */
#[CoversNothing]
final class FullCompositionIntegrationTest extends TestCase
{
    #[Test]
    public function complete_application_lifecycle_end_to_end(): void
    {
        $app = new Application('/tmp', new \FlintPHP\Framework\Config\ConfigRepository([
            'app' => ['name' => 'integration-test'],
            'database' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]));

        $app->bootstrapWith([
            new DatabaseBootstrapper(),
            new SecurityBootstrapper(),
            new ValidationBootstrapper(),
            new AuthenticationBootstrapper(),
            new AuthorizationBootstrapper(),
        ]);

        // Infrastructure resolved through the container.
        $connection = $app->container()->get(ConnectionInterface::class);
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $connection->execute('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $validator = $app->container()->get(Validator::class);
        $this->assertInstanceOf(Validator::class, $validator);

        // Authorization policy: require an authenticated identity.
        $app->container()->singleton(AuthorizerInterface::class, function () {
            return new class implements AuthorizerInterface {
                public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
                {
                    return $identity !== null;
                }
            };
        });

        // Authentication: fixed valid token -> user-42.
        $app->container()->singleton(UserProviderInterface::class, function () {
            return new class implements UserProviderInterface {
                public function retrieveByToken(string $token): ?IdentityInterface
                {
                    if (!hash_equals(hash('sha256', 'token-abc'), $token)) {
                        return null;
                    }

                    return new class implements IdentityInterface {
                        public function getIdentifier(): string|int
                        {
                            return 'user-42';
                        }
                    };
                }
            };
        });

        // Routes: public, protected (authn+authz), DB-backed, error paths.
        $app->router()->get('/public', fn (): Response => new Response('public-data'));

        $app->router()->get('/items', function (Request $request, Validator $v, ConnectionInterface $db): Response {
            $result = $v->validate($request->query(), ['name' => ['required', 'string', 'max:64']]);

            if (!$result->isValid()) {
                throw new HttpException(400, 'Validation failed');
            }

            $db->execute('INSERT INTO items (name) VALUES (?)', [$result->validated()['name']]);
            $id = (int) $db->pdo()->lastInsertId();

            return Response::json(['id' => $id, 'name' => $result->validated()['name']], 201);
        });

        $app->router()->get('/admin', fn (): Response => new Response('admin-data'), middleware: [
            RequireAuthenticationMiddleware::class,
            RequireAuthorizationMiddleware::class,
        ]);

        $app->router()->get('/boom', function (): never {
            throw new \RuntimeException('internal detail');
        });

        // 1. Public route.
        $public = $app->kernel()->handle(new Request('GET', '/public'));
        $this->assertSame(200, $public->status());
        $this->assertSame('public-data', $public->body());
        $this->assertSame('nosniff', $public->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $public->header('X-Frame-Options'));

        // 2. Validation + database write with prepared statement.
        $created = $app->kernel()->handle(
            new Request('GET', '/items', [], '', [], [], ['name' => 'flint'])
        );
        $this->assertSame(201, $created->status());
        $this->assertSame('{"id":1,"name":"flint"}', $created->body());

        // 3. Validation failure -> controlled 400.
        $invalid = $app->kernel()->handle(new Request('GET', '/items'));
        $this->assertSame(400, $invalid->status());

        $invalidDetail = $app->kernel()->handle(
            new Request('GET', '/items', [], '', [], [], ['name' => str_repeat('x', 100)])
        );
        $this->assertSame(400, $invalidDetail->status());
        $this->assertSame("Validation failed\n", $invalidDetail->body());
        $this->assertStringNotContainsString('x', $invalidDetail->body());

        // 4. Protected route: 401 (no token) -> 200 (valid token).
        $this->assertSame(401, $app->kernel()->handle(new Request('GET', '/admin'))->status());
        $authorized = $app->kernel()->handle(
            new Request('GET', '/admin', ['Authorization' => 'Bearer token-abc'])
        );
        $this->assertSame(200, $authorized->status());
        $this->assertSame('admin-data', $authorized->body());

        // 5. Unexpected exception -> generic 500, zero disclosure.
        $boom = $app->kernel()->handle(new Request('GET', '/boom'));
        $this->assertSame(500, $boom->status());
        $this->assertSame("Internal Server Error\n", $boom->body());
        $this->assertStringNotContainsString('internal detail', $boom->body());

        // 6. Routing errors: 404 and 405 with Allow header.
        $missing = $app->kernel()->handle(new Request('GET', '/nowhere'));
        $this->assertSame(404, $missing->status());

        $badMethod = $app->kernel()->handle(new Request('DELETE', '/public'));
        $this->assertSame(405, $badMethod->status());
        $this->assertSame('GET', $badMethod->header('Allow'));

        // 7. Idempotent boot; deterministic composition.
        $app->boot();
        $app->boot();
        $this->assertTrue($app->isBooted());
    }

    #[Test]
    public function kernel_can_still_be_constructed_manually_with_legacy_signature(): void
    {
        // BC regression pin: the pre-Application manual assembly path.
        $container = new \FlintPHP\Framework\Container\Container();
        $router = new \FlintPHP\Framework\Routing\Router();
        $router->get('/legacy', fn (): Response => new Response('legacy-ok'));

        $kernel = new Kernel($router, new MiddlewareStack(), new HandlerInvoker($container));

        $response = $kernel->handle(new Request('GET', '/legacy'));

        $this->assertSame(200, $response->status());
        $this->assertSame('legacy-ok', $response->body());
    }

    #[Test]
    public function two_applications_remain_fully_isolated(): void
    {
        $appA = new Application('/tmp');
        $appA->router()->get('/who', fn (): Response => new Response('A'));
        $appA->addMiddleware(TagAMiddleware::class);

        $appB = new Application('/tmp');
        $appB->router()->get('/who', fn (): Response => new Response('B'));

        $responseB = $appB->kernel()->handle(new Request('GET', '/who'));
        $responseA = $appA->kernel()->handle(new Request('GET', '/who'));

        // B must not observe A's middleware or routes, and vice versa.
        $this->assertSame('B', $responseB->body());
        $this->assertNull($responseB->header('X-Tag-A'));
        $this->assertSame('A', $responseA->body());
        $this->assertSame('A', $responseA->header('X-Tag-A'));
    }
}

final class TagAMiddleware implements \FlintPHP\Framework\Middleware\MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Tag-A', 'A');
    }
}
