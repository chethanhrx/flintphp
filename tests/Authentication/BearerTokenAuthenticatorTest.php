<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\BearerTokenAuthenticator;
use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Exception\MissingCredentialsException;
use FlintPHP\Framework\Authentication\IdentityInterface;
use FlintPHP\Framework\Authentication\UserProviderInterface;
use FlintPHP\Framework\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TestIdentity implements IdentityInterface
{
    public function __construct(private readonly int $id)
    {
    }

    public function getIdentifier(): string|int
    {
        return $this->id;
    }
}

class TestUserProvider implements UserProviderInterface
{
    /** @var array<string, IdentityInterface> */
    public array $tokens = [];

    public function retrieveByToken(string $token): ?IdentityInterface
    {
        return $this->tokens[$token] ?? null;
    }
}

#[CoversClass(BearerTokenAuthenticator::class)]
final class BearerTokenAuthenticatorTest extends TestCase
{
    private TestUserProvider $provider;
    private BearerTokenAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->provider = new TestUserProvider();
        $this->authenticator = new BearerTokenAuthenticator($this->provider);
    }

    #[Test]
    public function it_throws_missing_credentials_if_authorization_header_is_absent(): void
    {
        $request = new Request('GET', '/api/data');

        $this->expectException(MissingCredentialsException::class);
        $this->expectExceptionMessage('Authorization header is missing.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function it_throws_missing_credentials_if_scheme_is_not_bearer(): void
    {
        $request = new Request('GET', '/api/data');
        $request = $request->withHeader('Authorization', 'Basic dXNlcjpwYXNz');

        $this->expectException(MissingCredentialsException::class);
        $this->expectExceptionMessage('Authorization header must use the Bearer scheme.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function it_throws_invalid_credentials_if_token_is_empty(): void
    {
        $request = new Request('GET', '/api/data');
        $request = $request->withHeader('Authorization', 'Bearer ');

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Bearer token is empty.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function it_throws_invalid_credentials_if_token_does_not_match(): void
    {
        $request = new Request('GET', '/api/data');
        $request = $request->withHeader('Authorization', 'Bearer invalid_token_123');

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid Bearer token.');

        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function it_successfully_authenticates_and_returns_identity(): void
    {
        $plaintextToken = 'secret_token_123';
        $hashedToken = hash('sha256', $plaintextToken);
        
        $identity = new TestIdentity(42);
        $this->provider->tokens[$hashedToken] = $identity;

        $request = new Request('GET', '/api/data');
        $request = $request->withHeader('Authorization', 'Bearer ' . $plaintextToken);

        $result = $this->authenticator->authenticate($request);

        $this->assertSame($identity, $result);
        $this->assertSame(42, $result->getIdentifier());
    }

    #[Test]
    public function it_accepts_case_insensitive_bearer_schemes(): void
    {
        $plaintextToken = 'secret_token_123';
        $hashedToken = hash('sha256', $plaintextToken);
        
        $identity = new TestIdentity(42);
        $this->provider->tokens[$hashedToken] = $identity;

        $schemes = ['Bearer', 'bearer', 'BEARER', 'BeArEr'];

        foreach ($schemes as $scheme) {
            $request = new Request('GET', '/api/data');
            $request = $request->withHeader('Authorization', $scheme . ' ' . $plaintextToken);

            $result = $this->authenticator->authenticate($request);

            $this->assertSame($identity, $result, "Failed for scheme: {$scheme}");
            $this->assertSame(42, $result->getIdentifier(), "Failed for scheme: {$scheme}");
        }
    }
}
