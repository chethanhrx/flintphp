<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Exception\MissingCredentialsException;
use FlintPHP\Framework\Authentication\Jwt\HmacJwt;
use FlintPHP\Framework\Authentication\Jwt\JwtAuthenticator;
use FlintPHP\Framework\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JwtAuthenticator::class)]
final class JwtAuthenticatorTest extends TestCase
{
    #[Test]
    public function valid_token_yields_identity(): void
    {
        $token = HmacJwt::encode(['sub' => 'user-1', 'role' => 'admin'], 'secret', 60);
        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $token]);

        $identity = $auth->authenticate($request);

        $this->assertSame('user-1', $identity->getIdentifier());
        $this->assertSame('admin', $identity->claims()['role']);
    }

    #[Test]
    public function tampered_token_is_rejected(): void
    {
        $token = HmacJwt::encode(['sub' => 'user-1'], 'secret', 60);
        $token = substr($token, 0, -4) . 'xxxx';

        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $token]);

        $this->expectException(InvalidCredentialsException::class);
        $auth->authenticate($request);
    }

    #[Test]
    public function expired_token_is_rejected(): void
    {
        $token = HmacJwt::encode(['sub' => 'user-1', 'exp' => time() - 10], 'secret');
        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $token]);

        $this->expectException(InvalidCredentialsException::class);
        $auth->authenticate($request);
    }

    #[Test]
    public function leeway_accepts_recently_expired_token(): void
    {
        $token = HmacJwt::encode(['sub' => 'user-1', 'exp' => time() - 5], 'secret');
        $auth = new JwtAuthenticator('secret', leewaySeconds: 10);
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $token]);

        $identity = $auth->authenticate($request);
        $this->assertSame('user-1', $identity->getIdentifier());
    }

    #[Test]
    public function issuer_validation_succeeds_and_fails_appropriately(): void
    {
        $validToken = HmacJwt::encode(['sub' => 'user-1', 'iss' => 'auth.flint'], 'secret', 60);
        $auth = new JwtAuthenticator('secret', expectedIssuer: 'auth.flint');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $validToken]);

        $identity = $auth->authenticate($request);
        $this->assertSame('user-1', $identity->getIdentifier());

        $badToken = HmacJwt::encode(['sub' => 'user-1', 'iss' => 'other.issuer'], 'secret', 60);
        $badRequest = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $badToken]);

        $this->expectException(InvalidCredentialsException::class);
        $auth->authenticate($badRequest);
    }

    #[Test]
    public function audience_validation_enforces_expected_audience(): void
    {
        $token = HmacJwt::encode(['sub' => 'user-1', 'aud' => 'api.flint'], 'secret', 60);
        $auth = new JwtAuthenticator('secret', expectedAudience: 'wrong.audience');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $token]);

        $this->expectException(InvalidCredentialsException::class);
        $auth->authenticate($request);
    }

    #[Test]
    public function missing_sub_claim_is_rejected(): void
    {
        $token = HmacJwt::encode(['role' => 'admin'], 'secret', 60);
        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ' . $token]);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('JWT missing sub claim');
        $auth->authenticate($request);
    }

    #[Test]
    public function missing_authorization_header_throws_missing_credentials(): void
    {
        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/');

        $this->expectException(MissingCredentialsException::class);
        $this->expectExceptionMessage('Authorization header is missing');
        $auth->authenticate($request);
    }

    #[Test]
    public function non_bearer_authorization_header_throws_missing_credentials(): void
    {
        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Basic dXNlcjpwYXNz']);

        $this->expectException(MissingCredentialsException::class);
        $this->expectExceptionMessage('Bearer scheme');
        $auth->authenticate($request);
    }

    #[Test]
    public function empty_bearer_token_throws_invalid_credentials(): void
    {
        $auth = new JwtAuthenticator('secret');
        $request = new Request('GET', '/', headers: ['Authorization' => 'Bearer ']);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Bearer token is empty');
        $auth->authenticate($request);
    }

    #[Test]
    public function empty_secret_in_constructor_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must not be empty');

        new JwtAuthenticator('');
    }
}
