<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Session;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Session\CsrfMiddleware;
use FlintPHP\Framework\Session\CsrfTokenManager;
use FlintPHP\Framework\Session\Session;
use FlintPHP\Framework\Session\SessionCookieConfiguration;
use FlintPHP\Framework\Session\SessionMiddleware;
use FlintPHP\Framework\Session\Store\ArraySessionStore;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[CoversClass(SessionCookieConfiguration::class)]
#[CoversClass(SessionMiddleware::class)]
#[CoversClass(ArraySessionStore::class)]
#[CoversClass(CsrfTokenManager::class)]
#[CoversClass(CsrfMiddleware::class)]
final class SessionSecurityTest extends TestCase
{
    #[Test]
    public function session_exposes_explicit_mutable_data_operations(): void
    {
        $session = new Session(str_repeat('a', 64), ['user_id' => 42]);

        self::assertTrue($session->has('user_id'));
        self::assertSame(42, $session->get('user_id'));
        self::assertSame('fallback', $session->get('missing', 'fallback'));

        $session->set('theme', 'dark');
        self::assertSame('dark', $session->get('theme'));

        $session->remove('user_id');
        self::assertFalse($session->has('user_id'));

        $session->clear();
        self::assertSame([], $session->all());
    }

    #[Test]
    public function array_store_reads_and_deletes_session_payloads(): void
    {
        $store = new ArraySessionStore();
        $id = str_repeat('b', 64);
        $store->write($id, ['role' => 'member'], time() + 60);

        self::assertSame(['role' => 'member'], $store->read($id));

        $store->delete($id);
        self::assertNull($store->read($id));
    }

    #[Test]
    public function array_store_does_not_return_expired_sessions(): void
    {
        $store = new ArraySessionStore();
        $id = str_repeat('c', 64);
        $store->write($id, ['role' => 'member'], time() - 1);

        self::assertNull($store->read($id));
    }

    #[Test]
    public function cookie_configuration_defaults_to_host_only_secure_cookie(): void
    {
        $cookie = new SessionCookieConfiguration();
        $value = $cookie->serialize(str_repeat('d', 64));

        self::assertStringContainsString('__Host-flint_session=', $value);
        self::assertStringContainsString('; Path=/', $value);
        self::assertStringContainsString('; Secure', $value);
        self::assertStringContainsString('; HttpOnly', $value);
        self::assertStringContainsString('; SameSite=Lax', $value);
        self::assertStringNotContainsString('; Domain=', $value);
    }

    #[Test]
    public function cookie_configuration_rejects_insecure_host_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SessionCookieConfiguration(secure: false);
    }

    #[Test]
    public function middleware_creates_server_side_session_and_preserves_existing_cookies(): void
    {
        $store = new ArraySessionStore();
        $middleware = new SessionMiddleware($store);
        $request = new Request('GET', '/');

        $response = $middleware->process(
            $request,
            static function (Request $request): Response {
                $session = $request->attribute(SessionMiddleware::ATTRIBUTE);
                self::assertInstanceOf(Session::class, $session);
                $session->set('cart_count', 3);

                return new Response(
                    body: 'ok',
                    headers: ['Set-Cookie' => ['other=value'], 'X-Keep' => 'yes'],
                );
            },
        );

        $cookies = $response->headers()->getAll('Set-Cookie');
        self::assertCount(2, $cookies);
        self::assertSame('other=value', $cookies[0]);
        self::assertStringContainsString('__Host-flint_session=', $cookies[1]);
        self::assertSame('yes', $response->header('X-Keep'));

        preg_match('/__Host-flint_session=([a-f0-9]{64})/', $cookies[1], $matches);
        self::assertArrayHasKey(1, $matches);
        self::assertSame(['cart_count' => 3], $store->read($matches[1]));
    }

    #[Test]
    public function middleware_rejects_unknown_client_session_ids_and_issues_a_fresh_id(): void
    {
        $store = new ArraySessionStore();
        $middleware = new SessionMiddleware($store);
        $unknown = str_repeat('e', 64);
        $request = new Request('GET', '/', cookies: ['__Host-flint_session' => $unknown]);

        $response = $middleware->process(
            $request,
            static function (Request $request): Response {
                $session = $request->attribute(SessionMiddleware::ATTRIBUTE);
                self::assertInstanceOf(Session::class, $session);
                self::assertNotSame(str_repeat('e', 64), $session->id());

                return new Response();
            },
        );

        self::assertCount(1, $response->headers()->getAll('Set-Cookie'));
    }

    #[Test]
    public function csrf_token_is_random_stable_and_session_bound(): void
    {
        $tokens = new CsrfTokenManager();
        $first = new Session(str_repeat('a', 64));
        $second = new Session(str_repeat('b', 64));

        $token = $tokens->token($first);

        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        self::assertSame($token, $tokens->token($first));
        self::assertTrue($tokens->isValid($first, $token));
        self::assertFalse($tokens->isValid($second, $token));
        self::assertFalse($tokens->isValid($first, null));
        self::assertFalse($tokens->isValid($first, 'wrong'));
    }

    #[Test]
    public function csrf_middleware_allows_safe_methods_without_a_session(): void
    {
        $middleware = new CsrfMiddleware();
        $called = false;

        $response = $middleware->process(
            new Request('GET', '/'),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return new Response(body: 'ok');
            },
        );

        self::assertTrue($called);
        self::assertSame('ok', $response->body());
    }

    #[Test]
    public function csrf_middleware_rejects_missing_or_invalid_token(): void
    {
        $middleware = new CsrfMiddleware();
        $session = new Session(str_repeat('a', 64));
        (new CsrfTokenManager())->token($session);

        $response = $middleware->process(
            (new Request('POST', '/update'))->withAttribute(SessionMiddleware::ATTRIBUTE, $session),
            static fn (Request $request): Response => new Response(body: 'should not run'),
        );

        self::assertSame(419, $response->status());
        self::assertStringContainsString('csrf_token_mismatch', $response->body());
    }

    #[Test]
    public function csrf_middleware_accepts_a_valid_token_on_unsafe_methods(): void
    {
        $tokens = new CsrfTokenManager();
        $session = new Session(str_repeat('a', 64));
        $token = $tokens->token($session);
        $middleware = new CsrfMiddleware($tokens);
        $called = false;

        $response = $middleware->process(
            (new Request('PATCH', '/update', headers: ['X-CSRF-Token' => $token]))
                ->withAttribute(SessionMiddleware::ATTRIBUTE, $session),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return new Response(body: 'updated');
            },
        );

        self::assertTrue($called);
        self::assertSame('updated', $response->body());
    }

    #[Test]
    public function csrf_middleware_fails_fast_when_session_middleware_was_not_registered(): void
    {
        $this->expectException(LogicException::class);

        (new CsrfMiddleware())->process(
            new Request('POST', '/update'),
            static fn (Request $request): Response => new Response(),
        );
    }
}
