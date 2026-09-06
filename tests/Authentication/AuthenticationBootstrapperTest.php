<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\AuthenticationBootstrapper;
use FlintPHP\Framework\Authentication\AuthenticatorInterface;
use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Authentication\UserProviderInterface;
use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

#[CoversClass(AuthenticationBootstrapper::class)]
final class AuthenticationBootstrapperTest extends TestCase
{
    #[Test]
    public function it_registers_lazy_singleton_services(): void
    {
        $app = new Application('/tmp');

        $app->container()->singleton(UserProviderInterface::class, function () {
            return new class implements UserProviderInterface {
                public function retrieveByToken(string $token): ?IdentityInterface
                {
                    return null;
                }
            };
        });

        $app->bootstrapWith([new AuthenticationBootstrapper()]);

        $this->assertTrue($app->container()->has(AuthenticatorInterface::class));
        $this->assertTrue($app->container()->has(RequireAuthenticationMiddleware::class));

        $authenticator = $app->container()->get(AuthenticatorInterface::class);
        $this->assertInstanceOf(AuthenticatorInterface::class, $authenticator);

        // Singleton semantics: repeated resolution returns the same instance.
        $this->assertSame($authenticator, $app->container()->get(AuthenticatorInterface::class));
        $this->assertSame(
            $app->container()->get(RequireAuthenticationMiddleware::class),
            $app->container()->get(RequireAuthenticationMiddleware::class)
        );
    }

    #[Test]
    public function authenticator_resolution_fails_loudly_without_user_provider(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new AuthenticationBootstrapper()]);

        $this->expectException(NotFoundExceptionInterface::class);

        $app->container()->get(AuthenticatorInterface::class);
    }

    #[Test]
    public function middleware_is_not_instantiated_until_resolved(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new AuthenticationBootstrapper()]);

        // Bootstrapping alone must not resolve the authenticator chain:
        // without a UserProviderInterface bound, resolution would fail.
        // It must only fail when the service is actually requested.
        $this->expectNotToPerformAssertions();
        $app->boot();
    }

    #[Test]
    public function end_to_end_bearer_authentication_through_route_scoped_middleware(): void
    {
        $app = new Application('/tmp');
        $app->container()->singleton(UserProviderInterface::class, function () {
            return new class implements UserProviderInterface {
                public function retrieveByToken(string $token): ?IdentityInterface
                {
                    $expected = hash('sha256', 'valid-token');
                    if (!hash_equals($expected, $token)) {
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

        $app->bootstrapWith([new AuthenticationBootstrapper()]);

        $app->router()->get('/api/secure', function (Request $request): Response {
            $identity = $request->getAttribute('identity');

            return new Response('Hello ' . $identity->getIdentifier());
        }, middleware: [RequireAuthenticationMiddleware::class]);

        $app->router()->get('/api/open', fn (): Response => new Response('Public'));

        // Valid token -> handler executes with identity attribute attached.
        $validRequest = new Request(
            'GET',
            '/api/secure',
            ['Authorization' => 'Bearer valid-token']
        );

        $validResponse = $app->kernel()->handle($validRequest);
        $this->assertSame(200, $validResponse->status());
        $this->assertSame('Hello user-42', $validResponse->body());

        // Missing token -> 401, handler never executes.
        $missingResponse = $app->kernel()->handle(new Request('GET', '/api/secure'));
        $this->assertSame(401, $missingResponse->status());
        $this->assertSame('Bearer', $missingResponse->header('WWW-Authenticate'));

        // Invalid token -> 401.
        $invalidRequest = new Request('GET', '/api/secure', ['Authorization' => 'Bearer wrong-token']);
        $invalidResponse = $app->kernel()->handle($invalidRequest);
        $this->assertSame(401, $invalidResponse->status());

        // Unprotected route -> no authentication required.
        $openResponse = $app->kernel()->handle(new Request('GET', '/api/open'));
        $this->assertSame(200, $openResponse->status());
        $this->assertSame('Public', $openResponse->body());
    }

    #[Test]
    public function custom_authenticator_overrides_bearer_default(): void
    {
        $app = new Application('/tmp');

        $app->bootstrapWith([new AuthenticationBootstrapper()]);

        // Rebinding after bootstrapping is the documented override mechanism.
        $customAuthenticator = new class implements AuthenticatorInterface {
            public function authenticate(Request $request): IdentityInterface
            {
                return new class implements IdentityInterface {
                    public function getIdentifier(): string|int
                    {
                        return 'custom-identity';
                    }
                };
            }
        };

        $app->container()->singleton(AuthenticatorInterface::class, $customAuthenticator);

        $app->router()->get('/whoami', function (Request $request): Response {
            $identity = $request->getAttribute('identity');
            return new Response('id:' . $identity->getIdentifier());
        }, middleware: [RequireAuthenticationMiddleware::class]);

        $response = $app->kernel()->handle(new Request('GET', '/whoami'));

        $this->assertSame(200, $response->status());
        $this->assertSame('id:custom-identity', $response->body());
    }

    #[Test]
    public function invalid_credentials_exception_short_circuits_with_401(): void
    {
        $app = new Application('/tmp');

        $app->container()->singleton(UserProviderInterface::class, function () {
            return new class implements UserProviderInterface {
                public function retrieveByToken(string $token): ?IdentityInterface
                {
                    throw new InvalidCredentialsException('Token rejected by provider.');
                }
            };
        });

        $app->bootstrapWith([new AuthenticationBootstrapper()]);

        $app->router()->get('/protected', fn (): Response => new Response('Secret'), middleware: [
            RequireAuthenticationMiddleware::class,
        ]);

        $request = new Request('GET', '/protected', ['Authorization' => 'Bearer anything']);
        $response = $app->kernel()->handle($request);

        $this->assertSame(401, $response->status());
        $this->assertSame('{"error":"Unauthorized"}', $response->body());
    }
}
