<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http\Middleware;

use FlintPHP\Framework\Cache\ArrayCache;
use FlintPHP\Framework\Cache\CacheInterface;
use FlintPHP\Framework\Cache\Exception\CacheStorageException;
use FlintPHP\Framework\Http\Middleware\RateLimitMiddleware;
use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitMiddlewareTest extends TestCase
{
    private function simulateFromGlobals(array $server, ?TrustedProxyConfiguration $trustedProxies = null): Request
    {
        $originalServer = $_SERVER;
        $_SERVER = $server;

        $request = Request::fromGlobals($trustedProxies);

        $_SERVER = $originalServer;

        return $request;
    }

    #[Test]
    public function requests_below_limit_are_allowed_with_proper_headers(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 5, windowSeconds: 60);
        $request = new Request('GET', '/', clientIp: '192.168.1.1');

        $response1 = $mw->process($request, fn () => new Response('ok'));
        $this->assertSame(200, $response1->status());
        $this->assertSame('5', $response1->header('X-RateLimit-Limit'));
        $this->assertSame('4', $response1->header('X-RateLimit-Remaining'));

        $response2 = $mw->process($request, fn () => new Response('ok'));
        $this->assertSame(200, $response2->status());
        $this->assertSame('5', $response2->header('X-RateLimit-Limit'));
        $this->assertSame('3', $response2->header('X-RateLimit-Remaining'));
    }

    #[Test]
    public function request_at_limit_is_allowed_with_zero_remaining(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 2, windowSeconds: 60);
        $request = new Request('GET', '/', clientIp: '192.168.1.1');

        $response1 = $mw->process($request, fn () => new Response('ok'));
        $this->assertSame(200, $response1->status());
        $this->assertSame('1', $response1->header('X-RateLimit-Remaining'));

        $response2 = $mw->process($request, fn () => new Response('ok'));
        $this->assertSame(200, $response2->status());
        $this->assertSame('0', $response2->header('X-RateLimit-Remaining'));
    }

    #[Test]
    public function requests_beyond_limit_are_blocked_with_429_and_retry_after(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 2, windowSeconds: 30);
        $request = new Request('GET', '/', clientIp: '192.168.1.1');

        $mw->process($request, fn () => new Response('ok'));
        $mw->process($request, fn () => new Response('ok'));

        $blocked = $mw->process($request, fn () => new Response('ok'));
        $this->assertSame(429, $blocked->status());
        $this->assertSame('30', $blocked->header('Retry-After'));
        $this->assertSame('2', $blocked->header('X-RateLimit-Limit'));
        $this->assertSame('0', $blocked->header('X-RateLimit-Remaining'));

        $body = json_decode($blocked->body(), true);
        $this->assertIsArray($body);
        $this->assertSame('Too Many Requests', $body['error']['message'] ?? null);
        $this->assertSame('rate_limited', $body['error']['code'] ?? null);
    }

    #[Test]
    public function multiple_independent_client_keys_do_not_interfere(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 1, windowSeconds: 60);

        $clientA = new Request('GET', '/', clientIp: '10.0.0.1');
        $clientB = new Request('GET', '/', clientIp: '10.0.0.2');

        $resA1 = $mw->process($clientA, fn () => new Response('ok'));
        $this->assertSame(200, $resA1->status());

        $resA2 = $mw->process($clientA, fn () => new Response('ok'));
        $this->assertSame(429, $resA2->status());

        // Client B is still below limit
        $resB1 = $mw->process($clientB, fn () => new Response('ok'));
        $this->assertSame(200, $resB1->status());
        $this->assertSame('0', $resB1->header('X-RateLimit-Remaining'));
    }

    #[Test]
    public function custom_key_resolver_is_respected(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware(
            $cache,
            maxAttempts: 1,
            windowSeconds: 60,
            keyResolver: fn (Request $req) => $req->header('X-API-Key') ?? 'anon'
        );

        $reqUser1 = new Request('GET', '/', headers: ['X-API-Key' => 'key_user_1']);
        $reqUser2 = new Request('GET', '/', headers: ['X-API-Key' => 'key_user_2']);

        $res1 = $mw->process($reqUser1, fn () => new Response('ok'));
        $this->assertSame(200, $res1->status());

        $res1Blocked = $mw->process($reqUser1, fn () => new Response('ok'));
        $this->assertSame(429, $res1Blocked->status());

        $res2 = $mw->process($reqUser2, fn () => new Response('ok'));
        $this->assertSame(200, $res2->status());
    }

    #[Test]
    public function empty_or_null_key_resolver_falls_back_to_unknown(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware(
            $cache,
            maxAttempts: 1,
            windowSeconds: 60,
            keyResolver: fn () => ''
        );

        $req = new Request('GET', '/');
        $this->assertSame(200, $mw->process($req, fn () => new Response('ok'))->status());
        $this->assertSame(429, $mw->process($req, fn () => new Response('ok'))->status());
    }

    #[Test]
    public function rejects_invalid_configuration(): void
    {
        $cache = new ArrayCache();

        $this->expectException(InvalidArgumentException::class);
        new RateLimitMiddleware($cache, maxAttempts: 0);
    }

    #[Test]
    public function rejects_invalid_window_configuration(): void
    {
        $cache = new ArrayCache();

        $this->expectException(InvalidArgumentException::class);
        new RateLimitMiddleware($cache, maxAttempts: 10, windowSeconds: 0);
    }

    #[Test]
    public function spoofed_proxy_headers_are_ignored_when_proxy_is_not_trusted(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 1, windowSeconds: 60);

        // Attacker at untrusted IP 203.0.113.5 tries spoofing X-Forwarded-For to rotate identities
        $serverA = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ];
        $reqA = $this->simulateFromGlobals($serverA, null);

        $serverB = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.2',
        ];
        $reqB = $this->simulateFromGlobals($serverB, null);

        $this->assertSame('203.0.113.5', $reqA->clientIp());
        $this->assertSame('203.0.113.5', $reqB->clientIp());

        $resA = $mw->process($reqA, fn () => new Response('ok'));
        $this->assertSame(200, $resA->status());

        // Spoofed second request MUST be blocked because client IP is still 203.0.113.5
        $resB = $mw->process($reqB, fn () => new Response('ok'));
        $this->assertSame(429, $resB->status());
    }

    #[Test]
    public function trusted_proxy_resolves_true_client_ip_correctly(): void
    {
        $cache = new ArrayCache();
        $mw = new RateLimitMiddleware($cache, maxAttempts: 1, windowSeconds: 60);
        $trustedProxies = new TrustedProxyConfiguration(['10.0.0.1']);

        // Legitimate requests forwarded by trusted proxy 10.0.0.1
        $serverA = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ];
        $reqA = $this->simulateFromGlobals($serverA, $trustedProxies);

        $serverB = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.2',
        ];
        $reqB = $this->simulateFromGlobals($serverB, $trustedProxies);

        $this->assertSame('198.51.100.1', $reqA->clientIp());
        $this->assertSame('198.51.100.2', $reqB->clientIp());

        $this->assertSame(200, $mw->process($reqA, fn () => new Response('ok'))->status());
        $this->assertSame(200, $mw->process($reqB, fn () => new Response('ok'))->status());
    }

    #[Test]
    public function non_atomic_cache_driver_is_rejected_by_default(): void
    {
        // Cache driver that implements only CacheInterface, without AtomicCounterInterface
        $mockCache = new class implements CacheInterface {
            public array $data = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value, ?int $ttl = null): bool
            {
                $this->data[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->data[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->data = [];
                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->data[$key]);
            }
        };

        $mw = new RateLimitMiddleware($mockCache, maxAttempts: 2, windowSeconds: 60);
        $req = new Request('GET', '/', clientIp: '1.2.3.4');

        $reached = false;
        $response = $mw->process($req, function () use (&$reached) {
            $reached = true;
            return new Response('ok');
        });

        $this->assertSame(503, $response->status());
        $this->assertFalse($reached);
        $this->assertSame('rate_limit_unavailable', json_decode($response->body(), true)['error']['code'] ?? null);
    }

    #[Test]
    public function non_atomic_cache_fallback_requires_explicit_opt_in(): void
    {
        $mockCache = new class implements CacheInterface {
            public array $data = [];

            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value, ?int $ttl = null): bool { $this->data[$key] = $value; return true; }
            public function delete(string $key): bool { unset($this->data[$key]); return true; }
            public function clear(): bool { $this->data = []; return true; }
            public function has(string $key): bool { return isset($this->data[$key]); }
        };

        $mw = new RateLimitMiddleware($mockCache, maxAttempts: 2, windowSeconds: 60, allowNonAtomicFallback: true);
        $req = new Request('GET', '/', clientIp: '1.2.3.4');

        $this->assertSame(200, $mw->process($req, fn () => new Response('ok'))->status());
        $this->assertSame(200, $mw->process($req, fn () => new Response('ok'))->status());
        $this->assertSame(429, $mw->process($req, fn () => new Response('ok'))->status());
    }

    #[Test]
    public function non_atomic_cache_write_failure_fails_closed(): void
    {
        $cache = new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed { return 0; }
            public function set(string $key, mixed $value, ?int $ttl = null): bool { return false; }
            public function delete(string $key): bool { return true; }
            public function clear(): bool { return true; }
            public function has(string $key): bool { return false; }
        };

        $mw = new RateLimitMiddleware($cache, maxAttempts: 2, windowSeconds: 60, allowNonAtomicFallback: true);
        $req = new Request('GET', '/', clientIp: '1.2.3.4');
        $reached = false;

        $response = $mw->process($req, function () use (&$reached) {
            $reached = true;
            return new Response('ok');
        });

        $this->assertSame(429, $response->status());
        $this->assertFalse($reached);
    }

    #[Test]
    public function cache_failure_fails_closed_by_default_without_leaking_details():
    {
        $failingCache = new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed
            {
                throw new CacheStorageException('Secret redis connection credentials or internal path details');
            }

            public function set(string $key, mixed $value, ?int $ttl = null): bool
            {
                throw new CacheStorageException('Write error');
            }

            public function delete(string $key): bool { return false; }
            public function clear(): bool { return false; }
            public function has(string $key): bool { return false; }
        };

        $mw = new RateLimitMiddleware($failingCache, maxAttempts: 10, windowSeconds: 60, failClosed: true, allowNonAtomicFallback: true);
        $req = new Request('GET', '/', clientIp: '1.2.3.4');

        $response = $mw->process($req, fn () => new Response('should not reach here'));
        $this->assertSame(429, $response->status());

        $body = $response->body();
        $this->assertStringNotContainsString('Secret redis connection credentials', $body);
        $this->assertStringNotContainsString('internal path', $body);
    }

    #[Test]
    public function cache_failure_fails_open_when_configured(): void
    {
        $failingCache = new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed
            {
                throw new CacheStorageException('Storage failure');
            }

            public function set(string $key, mixed $value, ?int $ttl = null): bool { return false; }
            public function delete(string $key): bool { return false; }
            public function clear(): bool { return false; }
            public function has(string $key): bool { return false; }
        };

        $mw = new RateLimitMiddleware($failingCache, maxAttempts: 10, windowSeconds: 60, failClosed: false, allowNonAtomicFallback: true);
        $req = new Request('GET', '/', clientIp: '1.2.3.4');

        $reached = false;
        $response = $mw->process($req, function () use (&$reached) {
            $reached = true;
            return new Response('passed through');
        });

        $this->assertTrue($reached);
        $this->assertSame(200, $response->status());
        $this->assertSame('passed through', $response->body());
    }
}
