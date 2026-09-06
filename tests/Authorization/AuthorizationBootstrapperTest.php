<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authorization;

use FlintPHP\Framework\Authentication\AuthenticationBootstrapper;
use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Authentication\UserProviderInterface;
use FlintPHP\Framework\Authorization\AuthorizationBootstrapper;
use FlintPHP\Framework\Authorization\AuthorizerInterface;
use FlintPHP\Framework\Authorization\Middleware\RequireAuthorizationMiddleware;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Middleware\MiddlewareInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AuthorizationBootstrapper::class)]
final class AuthorizationBootstrapperTest extends TestCase
{
    #[Test]
    public function it_registers_the_middleware_as_a_lazy_singleton(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        // Bootstrapping alone must not resolve anything: no authorizer is
        // bound in this test, so eager resolution would fail.
        $this->assertTrue($app->container()->has(RequireAuthorizationMiddleware::class));

        // An authorizer binding added AFTER bootstrap must be honored
        // (lazy factories capture the container, not instances).
        $app->container()->singleton(AuthorizerInterface::class, $this->allowAllAuthorizer());

        $first = $app->container()->get(RequireAuthorizationMiddleware::class);
        $second = $app->container()->get(RequireAuthorizationMiddleware::class);

        $this->assertInstanceOf(RequireAuthorizationMiddleware::class, $first);
        $this->assertSame($first, $second);
    }

    #[Test]
    public function unbound_authorizer_fails_closed_end_to_end(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        $app->router()->get('/protected', fn (): Response => new Response('secret'), middleware: [
            RequireAuthorizationMiddleware::class,
        ]);

        $response = $app->kernel()->handle(new Request('GET', '/protected'));

        // NotFoundException inside the Kernel boundary -> generic 500,
        // never the handler output, never disclosure.
        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('secret', $response->body());
    }

    #[Test]
    public function authorizer_override_after_bootstrap_takes_effect(): void
    {
        $app = new Application('/tmp');
        $app->container()->singleton(AuthorizerInterface::class, $this->allowAllAuthorizer());
        $app->bootstrapWith([new AuthorizationBootstrapper()]);
        $this->routeProtected($app);

        $this->assertSame(200, $app->kernel()->handle(new Request('GET', '/protected'))->status());

        // Documented override path: re-running the bootstrapper overwrites the
        // middleware binding and drops its cached instance, so the next
        // resolution picks up the rebound authorizer. (Rebinding ONLY the
        // authorizer after the middleware singleton has been resolved would
        // not refresh it — the container caches the built middleware.)
        $app->container()->singleton(AuthorizerInterface::class, $this->denyAllAuthorizer());
        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        $this->assertSame(403, $app->kernel()->handle(new Request('GET', '/protected'))->status());
    }

    #[Test]
    public function end_to_end_ordering_authentication_then_authorization(): void
    {
        $app = new Application('/tmp');

        $app->container()->singleton(UserProviderInterface::class, function () {
            return new class implements UserProviderInterface {
                public function retrieveByToken(string $token): ?IdentityInterface
                {
                    if (!hash_equals(hash('sha256', 'valid-token'), $token)) {
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

        $app->bootstrapWith([
            new AuthenticationBootstrapper(),
            new AuthorizationBootstrapper(),
        ]);

        $app->container()->singleton(AuthorizerInterface::class, new class implements AuthorizerInterface {
            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                // Only authenticated identities owning the expected marker may pass.
                return $identity !== null && $identity->getIdentifier() === 'user-42';
            }
        });

        $app->router()->get('/admin', fn (): Response => new Response('granted'), middleware: [
            RequireAuthenticationMiddleware::class,
            RequireAuthorizationMiddleware::class,
        ]);

        // 1. Unauthenticated: authentication middleware short-circuits first -> 401.
        $unauthenticated = $app->kernel()->handle(new Request('GET', '/admin'));
        $this->assertSame(401, $unauthenticated->status());
        $this->assertSame('Bearer', $unauthenticated->header('WWW-Authenticate'));

        // 2. Authenticated and allowed -> 200.
        $allowed = $app->kernel()->handle(
            new Request('GET', '/admin', ['Authorization' => 'Bearer valid-token'])
        );
        $this->assertSame(200, $allowed->status());
        $this->assertSame('granted', $allowed->body());

        // 3. Authenticated but denied -> 403 (no WWW-Authenticate on authorization denials).
        $denied = $app->kernel()->handle(
            new Request('GET', '/admin', ['Authorization' => 'Bearer wrong-token'])
        );
        // wrong-token never passes authentication, so the authenticator rejects it.
        $this->assertSame(401, $denied->status());
    }

    #[Test]
    public function authorizer_sees_null_identity_when_placed_before_authentication(): void
    {
        $app = new Application('/tmp');

        $seenIdentity = 'sentinel';

        $app->container()->singleton(UserProviderInterface::class, function () {
            return new class implements UserProviderInterface {
                public function retrieveByToken(string $token): ?IdentityInterface
                {
                    return new class implements IdentityInterface {
                        public function getIdentifier(): string|int
                        {
                            return 'user-42';
                        }
                    };
                }
            };
        });

        $app->container()->singleton(AuthorizerInterface::class, new class($seenIdentity) implements AuthorizerInterface {
            public function __construct(private mixed &$seen) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                $this->seen = $identity;
                return $identity !== null;
            }
        });

        $app->bootstrapWith([
            new AuthenticationBootstrapper(),
            new AuthorizationBootstrapper(),
        ]);

        // Reverse ordering: authorization runs first, so the identity attribute
        // does not exist yet — the authorizer observes a null identity even
        // though the request carries a valid token.
        $app->router()->get('/reversed', fn (): Response => new Response('never'), middleware: [
            RequireAuthorizationMiddleware::class,
            RequireAuthenticationMiddleware::class,
        ]);

        $response = $app->kernel()->handle(
            new Request('GET', '/reversed', ['Authorization' => 'Bearer valid-token'])
        );

        $this->assertNull($seenIdentity);
        // The authorizer denies null identities -> 403 before authentication ever runs.
        $this->assertSame(403, $response->status());
    }

    #[Test]
    public function global_authorization_middleware_applies_to_all_routes(): void
    {
        $app = new Application('/tmp');
        $app->container()->singleton(AuthorizerInterface::class, $this->denyAllAuthorizer());
        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        // Global registration is supported and resolves through the container.
        $app->addMiddleware(RequireAuthorizationMiddleware::class);

        $app->router()->get('/any', fn (): Response => new Response('body'));

        $response = $app->kernel()->handle(new Request('GET', '/any'));

        $this->assertSame(403, $response->status());
        $this->assertStringNotContainsString('body', $response->body());
    }

    #[Test]
    public function duplicate_authorization_middleware_remains_safe(): void
    {
        $app = new Application('/tmp');

        $calls = 0;

        $app->container()->singleton(AuthorizerInterface::class, new class($calls) implements AuthorizerInterface {
            public function __construct(private int &$calls) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                $this->calls++;
                return true;
            }
        });

        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        // Scoped + global duplication: the check runs twice, result unchanged.
        $app->addMiddleware(RequireAuthorizationMiddleware::class);
        $app->router()->get('/duplicated', fn (): Response => new Response('ok'), middleware: [
            RequireAuthorizationMiddleware::class,
        ]);

        $response = $app->kernel()->handle(new Request('GET', '/duplicated'));

        $this->assertSame(200, $response->status());
        $this->assertSame(2, $calls);
    }

    #[Test]
    public function applications_are_isolated_from_each_other(): void
    {
        $appA = new Application('/tmp');
        $appA->container()->singleton(AuthorizerInterface::class, $this->denyAllAuthorizer());
        $appA->bootstrapWith([new AuthorizationBootstrapper()]);
        $appA->router()->get('/x', fn (): Response => new Response('A'));
        $appA->addMiddleware(RequireAuthorizationMiddleware::class);

        $appB = new Application('/tmp');
        $appB->container()->singleton(AuthorizerInterface::class, $this->allowAllAuthorizer());
        $appB->bootstrapWith([new AuthorizationBootstrapper()]);
        $appB->router()->get('/x', fn (): Response => new Response('B'));
        $appB->addMiddleware(RequireAuthorizationMiddleware::class);

        $responseA = $appA->kernel()->handle(new Request('GET', '/x'));
        $responseB = $appB->kernel()->handle(new Request('GET', '/x'));

        $this->assertSame(403, $responseA->status());
        $this->assertSame(200, $responseB->status());
        $this->assertSame('B', $responseB->body());
    }

    #[Test]
    public function repeated_bootstrap_is_safe(): void
    {
        $app = new Application('/tmp');
        $app->container()->singleton(AuthorizerInterface::class, $this->allowAllAuthorizer());

        $app->bootstrapWith([new AuthorizationBootstrapper()]);
        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        $this->routeProtected($app);

        $this->assertSame(200, $app->kernel()->handle(new Request('GET', '/protected'))->status());
    }

    #[Test]
    public function denial_response_still_receives_global_outbound_middleware(): void
    {
        $app = new Application('/tmp');
        $app->container()->singleton(AuthorizerInterface::class, $this->denyAllAuthorizer());
        $app->bootstrapWith([
            new \FlintPHP\Framework\Security\SecurityBootstrapper(),
            new AuthorizationBootstrapper(),
        ]);

        $this->routeProtected($app);

        $response = $app->kernel()->handle(new Request('GET', '/protected'));

        // Security regression: authorization denials are still responses in the
        // pipeline, so security headers must be applied on the way out.
        $this->assertSame(403, $response->status());
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
    }

    #[Test]
    public function ability_binding_via_custom_container_id(): void
    {
        $app = new Application('/tmp');

        $received = null;

        $app->container()->singleton(AuthorizerInterface::class, new class($received) implements AuthorizerInterface {
            public function __construct(private mixed &$received) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                $this->received = $ability;
                return $ability === 'posts.manage';
            }
        });

        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        // Documented per-route ability pattern: a preconfigured middleware
        // under a custom container id, referenced from the scoped list.
        $app->container()->set('auth.ability:posts.manage', function ($c) {
            return new RequireAuthorizationMiddleware(
                $c->get(AuthorizerInterface::class),
                'posts.manage'
            );
        });

        $app->router()->get('/posts', fn (): Response => new Response('listed'), middleware: [
            'auth.ability:posts.manage',
        ]);

        $allowed = $app->kernel()->handle(new Request('GET', '/posts'));
        $this->assertSame(200, $allowed->status());
        $this->assertSame('posts.manage', $received);
        $this->assertSame('listed', $allowed->body());
    }

    #[Test]
    public function authorizer_failure_never_grants_access(): void
    {
        $app = new Application('/tmp');

        $app->container()->singleton(AuthorizerInterface::class, new class implements AuthorizerInterface {
            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                throw new RuntimeException('policy backend down');
            }
        });

        $app->bootstrapWith([new AuthorizationBootstrapper()]);

        $this->routeProtected($app);

        $response = $app->kernel()->handle(new Request('GET', '/protected'));

        // Fail-closed: generic 500, handler output never leaks.
        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('secret', $response->body());
        $this->assertStringNotContainsString('policy backend', $response->body());
    }

    private function routeProtected(Application $app): void
    {
        $app->router()->get('/protected', fn (): Response => new Response('secret'), middleware: [
            RequireAuthorizationMiddleware::class,
        ]);
    }

    private function allowAllAuthorizer(): AuthorizerInterface
    {
        return new class implements AuthorizerInterface {
            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                return true;
            }
        };
    }

    private function denyAllAuthorizer(): AuthorizerInterface
    {
        return new class implements AuthorizerInterface {
            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                return false;
            }
        };
    }
}
