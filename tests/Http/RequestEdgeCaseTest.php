<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Edge-case tests for Request immutability and accessor behavior.
 *
 * Covers gaps identified in the Phase A audit:
 * - withHeader() preserves all other state (tested for Response, not Request)
 * - path() behavior with empty/unusual URIs
 * - withAttribute/withHeader return distinct instances
 */
#[CoversClass(Request::class)]
final class RequestEdgeCaseTest extends TestCase
{
    // ---------------------------------------------------------------
    // withHeader() preserves all other state
    // ---------------------------------------------------------------

    #[Test]
    public function with_header_preserves_all_request_properties(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/users?page=2',
            headers: ['Content-Type' => 'application/json'],
            body: '{"name":"test"}',
            server: ['SERVER_NAME' => 'localhost'],
            cookies: ['session' => 'abc123'],
            query: ['page' => '2'],
            attributes: ['user' => 'someone'],
            clientIp: '10.0.0.1',
        );

        $modified = $request->withHeader('X-Custom', 'value');

        // New header is set
        $this->assertSame('value', $modified->header('X-Custom'));

        // All other properties preserved
        $this->assertSame('POST', $modified->method());
        $this->assertSame('/users?page=2', $modified->uri());
        $this->assertSame('application/json', $modified->header('Content-Type'));
        $this->assertSame('{"name":"test"}', $modified->body());
        $this->assertSame('localhost', $modified->server('SERVER_NAME'));
        $this->assertSame('abc123', $modified->cookie('session'));
        $this->assertSame('2', $modified->query('page'));
        $this->assertSame('someone', $modified->attribute('user'));
        $this->assertSame('10.0.0.1', $modified->clientIp());

        // Original unchanged
        $this->assertNull($request->header('X-Custom'));
        $this->assertNotSame($request, $modified);
    }

    #[Test]
    public function with_header_replaces_existing_header(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: ['Accept' => 'text/html'],
        );

        $modified = $request->withHeader('Accept', 'application/json');

        $this->assertSame('application/json', $modified->header('Accept'));
        $this->assertSame('text/html', $request->header('Accept'));
    }

    // ---------------------------------------------------------------
    // path() edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function path_returns_slash_for_empty_uri(): void
    {
        $request = new Request(method: 'GET', uri: '');

        // parse_url('', PHP_URL_PATH) returns '' on PHP 8.x; our code falls back to '/'
        $this->assertSame('/', $request->path());
    }

    #[Test]
    public function path_handles_double_slashes(): void
    {
        $request = new Request(method: 'GET', uri: '/users//profile');

        // parse_url preserves consecutive slashes in the path component
        $this->assertSame('/users//profile', $request->path());

        // In protocol-relative URIs with host, the path is extracted properly
        $protocolRelative = new Request(method: 'GET', uri: '//example.com/users');
        $this->assertSame('/users', $protocolRelative->path());
    }

    #[Test]
    public function path_handles_dot_segments(): void
    {
        $request = new Request(method: 'GET', uri: '/users/../admin');

        // path() does NOT normalize; it returns the raw path
        $this->assertSame('/users/../admin', $request->path());
    }

    // ---------------------------------------------------------------
    // Immutability: chaining multiple mutations
    // ---------------------------------------------------------------

    #[Test]
    public function chained_with_methods_produce_independent_instances(): void
    {
        $base = new Request(method: 'GET', uri: '/');

        $r1 = $base->withAttribute('a', 1);
        $r2 = $r1->withAttribute('b', 2);
        $r3 = $r2->withHeader('X-Test', 'value');

        // Each instance is independent
        $this->assertFalse($base->hasAttribute('a'));
        $this->assertTrue($r1->hasAttribute('a'));
        $this->assertFalse($r1->hasAttribute('b'));
        $this->assertTrue($r2->hasAttribute('a'));
        $this->assertTrue($r2->hasAttribute('b'));
        $this->assertNull($r2->header('X-Test'));
        $this->assertSame('value', $r3->header('X-Test'));
    }

    // ---------------------------------------------------------------
    // query() with various empty/edge states
    // ---------------------------------------------------------------

    #[Test]
    public function query_returns_null_value_when_key_exists_with_null(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            query: ['key' => null],
        );

        // null is a valid value; should return null, NOT the default
        $this->assertNull($request->query('key', 'default'));
    }

    #[Test]
    public function query_returns_default_when_key_does_not_exist(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            query: ['existing' => 'value'],
        );

        $this->assertSame('fallback', $request->query('nonexistent', 'fallback'));
    }

    // ---------------------------------------------------------------
    // clientIp preservation
    // ---------------------------------------------------------------

    #[Test]
    public function client_ip_is_null_by_default(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->clientIp());
    }

    #[Test]
    public function client_ip_is_preserved_through_with_attribute(): void
    {
        $request = new Request(method: 'GET', uri: '/', clientIp: '192.168.1.1');
        $modified = $request->withAttribute('user', 'test');

        $this->assertSame('192.168.1.1', $modified->clientIp());
    }

    #[Test]
    public function client_ip_is_preserved_through_with_header(): void
    {
        $request = new Request(method: 'GET', uri: '/', clientIp: '10.0.0.1');
        $modified = $request->withHeader('X-Test', 'value');

        $this->assertSame('10.0.0.1', $modified->clientIp());
    }
}
