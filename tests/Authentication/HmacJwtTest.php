<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use FlintPHP\Framework\Authentication\Jwt\HmacJwt;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HmacJwt::class)]
final class HmacJwtTest extends TestCase
{
    private const SECRET = 'super-secret-key-12345';

    #[Test]
    public function it_encodes_and_decodes_valid_token(): void
    {
        $claims = ['sub' => 'user-42', 'name' => 'Alice'];
        $now = 1700000000;
        $token = HmacJwt::encode($claims, self::SECRET, 3600, $now);

        $decoded = HmacJwt::decode($token, self::SECRET, currentTime: $now);

        $this->assertSame('user-42', $decoded['sub']);
        $this->assertSame('Alice', $decoded['name']);
        $this->assertSame($now, $decoded['iat']);
        $this->assertSame($now + 3600, $decoded['exp']);
    }

    #[Test]
    public function it_rejects_empty_secret_on_encode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must not be empty');

        HmacJwt::encode(['sub' => '1'], '');
    }

    #[Test]
    public function it_rejects_empty_secret_on_decode(): void
    {
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must not be empty');

        HmacJwt::decode($token, '');
    }

    #[Test]
    public function it_rejects_negative_leeway(): void
    {
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('leewaySeconds must be >= 0');

        HmacJwt::decode($token, self::SECRET, leewaySeconds: -1);
    }

    #[Test]
    public function it_rejects_invalid_ttl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT ttlSeconds must be > 0');

        HmacJwt::encode(['sub' => '1'], self::SECRET, ttlSeconds: 0);
    }

    #[Test]
    public function it_rejects_token_exceeding_max_size(): void
    {
        $hugeToken = str_repeat('a', 8193);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('invalid token size');

        HmacJwt::decode($hugeToken, self::SECRET);
    }

    #[Test]
    public function it_rejects_token_with_invalid_segment_count(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('exactly 3 segments');

        HmacJwt::decode('header.payload', self::SECRET);
    }

    #[Test]
    public function it_rejects_token_with_four_segments(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('exactly 3 segments');

        HmacJwt::decode('header.payload.sig.extra', self::SECRET);
    }

    #[Test]
    public function it_rejects_non_base64url_characters(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('invalid characters in token segments');

        HmacJwt::decode('head+er.pay/load.sig=', self::SECRET);
    }

    #[Test]
    public function it_rejects_tampered_signature(): void
    {
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET);
        $parts = explode('.', $token);
        $parts[2] = 'tampered' . substr($parts[2], 8);
        $tampered = implode('.', $parts);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        HmacJwt::decode($tampered, self::SECRET);
    }

    #[Test]
    public function it_rejects_tampered_payload(): void
    {
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET);
        $parts = explode('.', $token);
        // Replace payload with different base64url data
        $parts[1] = rtrim(strtr(base64_encode(json_encode(['sub' => 'hacked', 'exp' => time() + 1000])), '+/', '-_'), '=');
        $tampered = implode('.', $parts);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        HmacJwt::decode($tampered, self::SECRET);
    }

    #[Test]
    public function it_rejects_none_algorithm_even_if_signature_matches(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => time() + 1000])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Unsupported JWT algorithm');

        HmacJwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_unsupported_algorithm(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => time() + 1000])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Unsupported JWT algorithm');

        HmacJwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_invalid_jwt_type_header(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'NOT_JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => time() + 1000])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT type header');

        HmacJwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_missing_exp_claim(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        // Payload has sub but no exp
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1'])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Missing or invalid JWT exp claim');

        HmacJwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_non_numeric_exp_claim(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => 'never-expires'])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Missing or invalid JWT exp claim');

        HmacJwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_expired_token(): void
    {
        $now = 1700000000;
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET, 60, currentTime: $now - 120);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('JWT has expired');

        HmacJwt::decode($token, self::SECRET, currentTime: $now);
    }

    #[Test]
    public function it_rejects_token_at_exact_expiration_boundary(): void
    {
        $now = 1700000000;
        // Expires exactly at $now
        $token = HmacJwt::encode(['sub' => '1', 'exp' => $now], self::SECRET, currentTime: $now - 60);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('JWT has expired');

        // Evaluating at exactly $now (expiration boundary) must reject
        HmacJwt::decode($token, self::SECRET, currentTime: $now);
    }

    #[Test]
    public function it_accepts_token_one_second_before_expiration(): void
    {
        $now = 1700000000;
        $token = HmacJwt::encode(['sub' => '1', 'exp' => $now], self::SECRET, currentTime: $now - 60);

        // Evaluating at $now - 1 must pass
        $decoded = HmacJwt::decode($token, self::SECRET, currentTime: $now - 1);
        $this->assertSame('1', $decoded['sub']);
    }

    #[Test]
    public function it_honors_clock_skew_leeway_for_expiration(): void
    {
        $now = 1700000000;
        // Expired 5 seconds ago
        $token = HmacJwt::encode(['sub' => '1', 'exp' => $now - 5], self::SECRET, currentTime: $now - 60);

        // Fails with 0 leeway
        $failed = false;
        try {
            HmacJwt::decode($token, self::SECRET, leewaySeconds: 0, currentTime: $now);
        } catch (InvalidCredentialsException) {
            $failed = true;
        }
        $this->assertTrue($failed);

        // Passes with 10 seconds leeway
        $decoded = HmacJwt::decode($token, self::SECRET, leewaySeconds: 10, currentTime: $now);
        $this->assertSame('1', $decoded['sub']);
    }

    #[Test]
    public function it_validates_nbf_claim_in_the_future(): void
    {
        $now = 1700000000;
        $token = HmacJwt::encode(['sub' => '1', 'nbf' => $now + 60], self::SECRET, currentTime: $now);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('JWT cannot be used yet (nbf)');

        HmacJwt::decode($token, self::SECRET, currentTime: $now);
    }

    #[Test]
    public function it_accepts_valid_nbf_claim(): void
    {
        $now = 1700000000;
        $token = HmacJwt::encode(['sub' => '1', 'nbf' => $now - 10], self::SECRET, currentTime: $now);

        $decoded = HmacJwt::decode($token, self::SECRET, currentTime: $now);
        $this->assertSame('1', $decoded['sub']);
    }

    #[Test]
    public function it_rejects_malformed_nbf_claim(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => 1700003600, 'nbf' => 'invalid'])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT nbf claim');

        HmacJwt::decode($token, self::SECRET, currentTime: 1700000000);
    }

    #[Test]
    public function it_validates_future_iat_claim(): void
    {
        $now = 1700000000;
        $token = HmacJwt::encode(['sub' => '1', 'iat' => $now + 60], self::SECRET, currentTime: $now);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('JWT iat is in the future');

        HmacJwt::decode($token, self::SECRET, currentTime: $now);
    }

    #[Test]
    public function it_enforces_expected_issuer(): void
    {
        $token = HmacJwt::encode(['sub' => '1', 'iss' => 'https://auth.example.com'], self::SECRET);

        // Matching issuer passes
        $decoded = HmacJwt::decode($token, self::SECRET, expectedIssuer: 'https://auth.example.com');
        $this->assertSame('1', $decoded['sub']);

        // Mismatched issuer throws
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT issuer');
        HmacJwt::decode($token, self::SECRET, expectedIssuer: 'https://other.example.com');
    }

    #[Test]
    public function it_rejects_missing_issuer_when_required(): void
    {
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT issuer');

        HmacJwt::decode($token, self::SECRET, expectedIssuer: 'https://auth.example.com');
    }

    #[Test]
    public function it_enforces_expected_audience_string(): void
    {
        $token = HmacJwt::encode(['sub' => '1', 'aud' => 'api-gateway'], self::SECRET);

        $decoded = HmacJwt::decode($token, self::SECRET, expectedAudience: 'api-gateway');
        $this->assertSame('1', $decoded['sub']);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT audience');
        HmacJwt::decode($token, self::SECRET, expectedAudience: 'different-api');
    }

    #[Test]
    public function it_enforces_expected_audience_array(): void
    {
        $token = HmacJwt::encode(['sub' => '1', 'aud' => ['api-gateway', 'reporting-api']], self::SECRET);

        $decoded = HmacJwt::decode($token, self::SECRET, expectedAudience: 'reporting-api');
        $this->assertSame('1', $decoded['sub']);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT audience');
        HmacJwt::decode($token, self::SECRET, expectedAudience: 'billing-api');
    }

    #[Test]
    public function it_rejects_wrong_secret(): void
    {
        $token = HmacJwt::encode(['sub' => '1'], self::SECRET);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        HmacJwt::decode($token, 'wrong-secret');
    }

    #[Test]
    public function it_rejects_numeric_string_exp_that_could_overflow_integer_cast(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'exp' => '1e999'])), '+/', '-_'), '=');
        $data = $header . '.' . $payload;
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $data, self::SECRET, true)), '+/', '-_'), '=');
        $token = $data . '.' . $sig;

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Missing or invalid JWT exp claim');

        HmacJwt::decode($token, self::SECRET, currentTime: 1700000000);
    }

    #[Test]
    public function it_rejects_numeric_string_nbf_claim(): void
    {
        $token = HmacJwt::encode(['sub' => '1', 'nbf' => '1700000000'], self::SECRET);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT nbf claim');

        HmacJwt::decode($token, self::SECRET);
    }

    #[Test]
    public function it_rejects_numeric_string_iat_claim(): void
    {
        $token = HmacJwt::encode(['sub' => '1', 'iat' => '1700000000'], self::SECRET);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid JWT iat claim');

        HmacJwt::decode($token, self::SECRET);
    }
}
