<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Authentication\Jwt;

use FlintPHP\Framework\Authentication\Exception\InvalidCredentialsException;
use InvalidArgumentException;

/**
 * Minimal HS256 JWT encode/decode. No third-party dependency.
 *
 * Only HMAC-SHA256 is supported. Always set exp.
 */
final class HmacJwt
{
    /**
     * @param array<string, mixed> $claims
     */
    public static function encode(array $claims, string $secret, int $ttlSeconds = 3600): string
    {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT secret must not be empty');
        }

        $now = time();
        $claims['iat'] = $claims['iat'] ?? $now;
        $claims['exp'] = $claims['exp'] ?? ($now + $ttlSeconds);

        $header = self::b64(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = self::b64($claims);
        $sig = self::sign($header . '.' . $payload, $secret);

        return $header . '.' . $payload . '.' . $sig;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, string $secret): array
    {
        if ($secret === '') {
            throw new InvalidArgumentException('JWT secret must not be empty');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidCredentialsException('Malformed JWT');
        }

        [$headerB64, $payloadB64, $sig] = $parts;

        $expected = self::sign($headerB64 . '.' . $payloadB64, $secret);
        if (!hash_equals($expected, $sig)) {
            throw new InvalidCredentialsException('Invalid JWT signature');
        }

        $header = self::ub64($headerB64);
        if (($header['alg'] ?? null) !== 'HS256') {
            throw new InvalidCredentialsException('Unsupported JWT algorithm');
        }

        $payload = self::ub64($payloadB64);

        if (isset($payload['exp']) && is_numeric($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new InvalidCredentialsException('JWT has expired');
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private static function b64(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array<string, mixed> */
    private static function ub64(string $data): array
    {
        $padded = strtr($data, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $raw = base64_decode($padded, true);
        if ($raw === false) {
            throw new InvalidCredentialsException('Invalid JWT encoding');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidCredentialsException('Invalid JWT payload');
        }

        return $decoded;
    }

    private static function sign(string $data, string $secret): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $secret, true)), '+/', '-_'), '=');
    }
}
