<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Jwt\HmacJwt;
use FlintPHP\Framework\Authentication\Jwt\JwtAuthenticator;
use FlintPHP\Framework\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
