<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Jwt;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use InvalidArgumentException;
use JsonException;

/**
 * Hardened HS256 JWT encode/decode.
 *
 * Security guarantees:
 * - Only HMAC-SHA256 (HS256) is supported; algorithm confusion and 'none' are rejected.
 * - Strict Base64URL and JSON structure verification.
 * - Constant-time signature comparison using hash_equals().
 * - Exp claim is strictly required: missing or non-numeric exp is rejected.
 * - Expiration boundary enforcement (current time >= exp is expired) with optional clock skew leeway.
 * - nbf, iat, iss, and aud validation with type checking.
 * - Maximum token size bounded at 8,192 bytes.
 * - Fail-closed error handling with zero secret leakage.
 */
final class HmacJwt
{
    private const MAX_TOKEN_BYTES = 8192;
    private const SUPPORTED_ALG = 'HS256';

    /**
     * Encode claims into a signed HS256 JWT token.
     *
     * @param array<string, mixed> $claims
     * @throws InvalidArgumentException
     */
    public static function encode(
        array $claims,
        string $secret,
        int $ttlSeconds = 3600,
        ?int $currentTime = null,
    ): string {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT secret must not be empty');
        }

        if ($ttlSeconds <= 0) {
            throw new InvalidArgumentException('JWT ttlSeconds must be > 0');
        }

        $now = $currentTime ?? time();
        $claims['iat'] = $claims['iat'] ?? $now;
        $claims['exp'] = $claims['exp'] ?? ($now + $ttlSeconds);

        $header = self::b64(['alg' => self::SUPPORTED_ALG, 'typ' => 'JWT']);
        $payload = self::b64($claims);
        $sig = self::sign($header . '.' . $payload, $secret);

        return $header . '.' . $payload . '.' . $sig;
    }

    /**
     * Decode and validate an HS256 JWT token.
     *
     * @return array<string, mixed> The validated claims
     * @throws InvalidArgumentException If secret or arguments are invalid
     * @throws InvalidCredentialsException If the token is invalid, expired, or tampered with
     */
    public static function decode(
        string $token,
        string $secret,
        int $leewaySeconds = 0,
        ?string $expectedIssuer = null,
        ?string $expectedAudience = null,
        ?int $currentTime = null,
    ): array {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT secret must not be empty');
        }

        if ($leewaySeconds < 0) {
            throw new InvalidArgumentException('leewaySeconds must be >= 0');
        }

        if (strlen($token) === 0 || strlen($token) > self::MAX_TOKEN_BYTES) {
            throw new InvalidCredentialsException('Malformed JWT: invalid token size');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidCredentialsException('Malformed JWT: must contain exactly 3 segments');
        }

        [$headerB64, $payloadB64, $sig] = $parts;

        if (
            preg_match('/\A[a-zA-Z0-9_\-]+\z/', $headerB64) !== 1
            || preg_match('/\A[a-zA-Z0-9_\-]+\z/', $payloadB64) !== 1
            || preg_match('/\A[a-zA-Z0-9_\-]+\z/', $sig) !== 1
        ) {
            throw new InvalidCredentialsException('Malformed JWT: invalid characters in token segments');
        }

        // 1. Signature validation (constant-time)
        $expected = self::sign($headerB64 . '.' . $payloadB64, $secret);
        if (!hash_equals($expected, $sig)) {
            throw new InvalidCredentialsException('Invalid JWT signature');
        }

        // 2. Header validation
        $header = self::ub64($headerB64);
        $alg = $header['alg'] ?? null;
        if ($alg !== self::SUPPORTED_ALG) {
            throw new InvalidCredentialsException('Unsupported JWT algorithm: expected ' . self::SUPPORTED_ALG);
        }

        if (isset($header['typ'])) {
            if (!is_string($header['typ']) || strtoupper($header['typ']) !== 'JWT') {
                throw new InvalidCredentialsException('Invalid JWT type header');
            }
        }

        // 3. Payload decoding & claims validation
        $payload = self::ub64($payloadB64);
        $now = $currentTime ?? time();

        // Enforce required expiration
        if (!array_key_exists('exp', $payload) || (!is_int($payload['exp']) && (!is_string($payload['exp']) || !is_numeric($payload['exp'])))) {
            throw new InvalidCredentialsException('Missing or invalid JWT exp claim');
        }

        $exp = (int) $payload['exp'];
        if ($now >= $exp + $leewaySeconds) {
            throw new InvalidCredentialsException('JWT has expired');
        }

        // Validate nbf (not before) if present
        if (array_key_exists('nbf', $payload)) {
            if (!is_int($payload['nbf']) && (!is_string($payload['nbf']) || !is_numeric($payload['nbf']))) {
                throw new InvalidCredentialsException('Invalid JWT nbf claim');
            }

            $nbf = (int) $payload['nbf'];
            if ($now < $nbf - $leewaySeconds) {
                throw new InvalidCredentialsException('JWT cannot be used yet (nbf)');
            }
        }

        // Validate iat (issued at) if present
        if (array_key_exists('iat', $payload)) {
            if (!is_int($payload['iat']) && (!is_string($payload['iat']) || !is_numeric($payload['iat']))) {
                throw new InvalidCredentialsException('Invalid JWT iat claim');
            }

            $iat = (int) $payload['iat'];
            if ($iat > $now + $leewaySeconds) {
                throw new InvalidCredentialsException('JWT iat is in the future');
            }
        }

        // Validate issuer if required
        if ($expectedIssuer !== null) {
            if (!isset($payload['iss']) || !is_string($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
                throw new InvalidCredentialsException('Invalid JWT issuer');
            }
        }

        // Validate audience if required
        if ($expectedAudience !== null) {
            $aud = $payload['aud'] ?? null;
            $validAud = false;
            if (is_string($aud) && $aud === $expectedAudience) {
                $validAud = true;
            } elseif (is_array($aud) && in_array($expectedAudience, $aud, true)) {
                $validAud = true;
            }

            if (!$validAud) {
                throw new InvalidCredentialsException('Invalid JWT audience');
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function b64(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    private static function ub64(string $data): array
    {
        if ($data === '') {
            throw new InvalidCredentialsException('Invalid JWT payload: segment is empty');
        }

        $padded = strtr($data, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $raw = base64_decode($padded, true);
        if ($raw === false) {
            throw new InvalidCredentialsException('Invalid JWT encoding');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidCredentialsException('Invalid JWT JSON structure');
        }

        if (!is_array($decoded)) {
            throw new InvalidCredentialsException('Invalid JWT payload: expected JSON object');
        }

        return $decoded;
    }

    private static function sign(string $data, string $secret): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $secret, true)), '+/', '-_'), '=');
    }
}
