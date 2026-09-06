<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authorization;

use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authorization\AuthorizerInterface;
use FlintPHP\Framework\Authorization\Middleware\RequireAuthorizationMiddleware;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RequireAuthorizationMiddleware::class)]
final class RequireAuthorizationMiddlewareTest extends TestCase
{
    #[Test]
    public function allow_passes_request_through_unchanged(): void
    {
        $request = new Request('GET', '/resource');
        $received = null;

        $middleware = new RequireAuthorizationMiddleware(
            $this->authorizerReturning(true)
        );

        $response = $middleware->process(
            $request,
            function (Request $req) use (&$received): Response {
                $received = $req;
                return new Response('ok');
            }
        );

        $this->assertSame(200, $response->status());
        $this->assertSame('ok', $response->body());
        // Immutability: the very same Request instance flows through.
        $this->assertSame($request, $received);
    }

    #[Test]
    public function deny_short_circuits_with_fixed_403(): void
    {
        $nextCalled = false;

        $middleware = new RequireAuthorizationMiddleware(
            $this->authorizerReturning(false)
        );

        $response = $middleware->process(
            new Request('GET', '/resource'),
            function () use (&$nextCalled): Response {
                $nextCalled = true;
                return new Response('secret');
            }
        );

        $this->assertSame(403, $response->status());
        $this->assertSame('{"error":"Forbidden"}', $response->body());
        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertNull($response->header('WWW-Authenticate'));
        $this->assertFalse($nextCalled);
    }

    #[Test]
    public function authorizer_exception_propagates_unchanged(): void
    {
        $middleware = new RequireAuthorizationMiddleware(new class implements AuthorizerInterface {
            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                throw new RuntimeException('policy store unavailable');
            }
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('policy store unavailable');

        $middleware->process(new Request('GET', '/resource'), fn (): Response => new Response('never'));
    }

    #[Test]
    public function missing_identity_attribute_forwards_null_identity(): void
    {
        $receivedIdentity = 'sentinel';

        $middleware = new RequireAuthorizationMiddleware(new class($receivedIdentity) implements AuthorizerInterface {
            public function __construct(private mixed &$received) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                $this->received = $identity;
                return true;
            }
        });

        $middleware->process(new Request('GET', '/resource'), fn (): Response => new Response());

        $this->assertNull($receivedIdentity);
    }

    #[Test]
    public function authenticated_identity_is_forwarded(): void
    {
        $identity = new class implements IdentityInterface {
            public function getIdentifier(): string|int
            {
                return 'user-9';
            }
        };

        $received = null;

        $middleware = new RequireAuthorizationMiddleware(new class($received) implements AuthorizerInterface {
            public function __construct(private mixed &$received) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                $this->received = $identity;
                return true;
            }
        });

        $request = (new Request('GET', '/resource'))->withAttribute('identity', $identity);

        $middleware->process($request, fn (): Response => new Response());

        $this->assertSame($identity, $received);
    }

    #[Test]
    public function non_identity_attribute_throws_logic_exception(): void
    {
        $middleware = new RequireAuthorizationMiddleware($this->authorizerReturning(true));

        $request = (new Request('GET', '/resource'))->withAttribute('identity', 'not-an-identity');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement');

        $middleware->process($request, fn (): Response => new Response());
    }

    #[Test]
    public function explicit_null_identity_attribute_is_a_contract_violation(): void
    {
        // Absence of the attribute means "no identity" (null is forwarded to
        // the authorizer). A PRESENT attribute with a null value is a developer
        // contract violation, exactly like any other non-IdentityInterface
        // value: fail loudly instead of silently treating it as anonymous.
        $middleware = new RequireAuthorizationMiddleware($this->authorizerReturning(true));

        $request = (new Request('GET', '/resource'))->withAttribute('identity', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must implement');

        $middleware->process($request, fn (): Response => new Response());
    }

    #[Test]
    public function ability_is_forwarded_verbatim(): void
    {
        $receivedAbility = null;

        $middleware = new RequireAuthorizationMiddleware(
            new class($receivedAbility) implements AuthorizerInterface {
                public function __construct(private mixed &$received) {}

                public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
                {
                    $this->received = $ability;
                    return true;
                }
            },
            'posts.manage'
        );

        $middleware->process(new Request('GET', '/resource'), fn (): Response => new Response());

        $this->assertSame('posts.manage', $receivedAbility);
    }

    #[Test]
    public function empty_ability_is_forwarded_verbatim(): void
    {
        $receivedAbility = 'sentinel';

        $middleware = new RequireAuthorizationMiddleware(new class($receivedAbility) implements AuthorizerInterface {
            public function __construct(private mixed &$received) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                $this->received = $ability;
                return true;
            }
        });

        $middleware->process(new Request('GET', '/resource'), fn (): Response => new Response());

        $this->assertSame('', $receivedAbility);
    }

    #[Test]
    public function middleware_never_mutates_request_attributes(): void
    {
        $request = (new Request('GET', '/resource'))->withAttribute('identity', new class implements IdentityInterface {
            public function getIdentifier(): string|int
            {
                return 'user-1';
            }
        });

        $middleware = new RequireAuthorizationMiddleware($this->authorizerReturning(true));

        $middleware->process($request, fn (): Response => new Response());

        $this->assertSame(['identity'], array_keys($request->attributes()));
    }

    private function authorizerReturning(bool $decision): AuthorizerInterface
    {
        return new class($decision) implements AuthorizerInterface {
            public function __construct(private readonly bool $decision) {}

            public function authorize(?IdentityInterface $identity, string $ability = '', mixed $resource = null): bool
            {
                return $this->decision;
            }
        };
    }
}
