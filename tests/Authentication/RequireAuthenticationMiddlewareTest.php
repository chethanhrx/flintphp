<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\AuthenticatorInterface;
use FlintPHP\Framework\Authentication\Exception\MissingCredentialsException;
use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authentication\Middleware\RequireAuthenticationMiddleware;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MiddlewareTestIdentity implements IdentityInterface
{
    public function getIdentifier(): string|int
    {
        return 99;
    }
}

class MockAuthenticator implements AuthenticatorInterface
{
    public bool $shouldThrow = false;
    public ?IdentityInterface $identity = null;

    public function authenticate(Request $request): IdentityInterface
    {
        if ($this->shouldThrow) {
            throw new MissingCredentialsException();
        }

        return $this->identity ?? new MiddlewareTestIdentity();
    }
}

#[CoversClass(RequireAuthenticationMiddleware::class)]
final class RequireAuthenticationMiddlewareTest extends TestCase
{
    private MockAuthenticator $authenticator;
    private RequireAuthenticationMiddleware $middleware;
    private ?Request $handledRequest = null;
    private \Closure $next;

    protected function setUp(): void
    {
        $this->authenticator = new MockAuthenticator();
        $this->middleware = new RequireAuthenticationMiddleware($this->authenticator);
        
        $this->handledRequest = null;
        $this->next = function (Request $request) {
            $this->handledRequest = $request;
            return new Response('OK', 200);
        };
    }

    #[Test]
    public function it_returns_401_response_when_authentication_fails(): void
    {
        $this->authenticator->shouldThrow = true;
        
        $request = new Request('GET', '/api/secure');
        $response = $this->middleware->process($request, $this->next);

        $this->assertSame(401, $response->status());
        $this->assertSame('Bearer', $response->header('WWW-Authenticate'));
        $this->assertSame('{"error":"Unauthorized"}', $response->body());
        $this->assertNull($this->handledRequest); // Next handler was not called
    }

    #[Test]
    public function it_attaches_identity_to_request_and_calls_next_handler_when_successful(): void
    {
        $identity = new MiddlewareTestIdentity();
        $this->authenticator->identity = $identity;

        $request = new Request('GET', '/api/secure');
        $response = $this->middleware->process($request, $this->next);

        $this->assertSame(200, $response->status());
        $this->assertNotNull($this->handledRequest);
        
        // Assert the request passed to the next handler has the identity attribute
        $this->assertSame($identity, $this->handledRequest->getAttribute('identity'));
    }
}
