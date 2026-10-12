<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestEdgeCaseTest extends TestCase
{
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

        $this->assertSame('value', $modified->header('X-Custom'));
        $this->assertSame('POST', $modified->method());
        $this->assertSame('/users?page=2', $modified->uri());
        $this->assertSame('application/json', $modified->header('Content-Type'));
        $this->assertSame('{"name":"test"}', $modified->body());
        $this->assertSame('localhost', $modified->server('SERVER_NAME'));
        $this->assertSame('abc123', $modified->cookie('session'));
        $this->assertSame('2', $modified->query('page'));
        $this->assertSame('someone', $modified->attribute('user'));
        $this->assertSame('10.0.0.1', $modified->clientIp());
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

    #[Test]
    public function path_returns_slash_for_empty_uri(): void
    {
        $request = new Request(method: 'GET', uri: '');
        $this->assertSame('/', $request->path());
    }

    #[Test]
    public function path_handles_double_slashes(): void
    {
        $request = new Request(method: 'GET', uri: '/users//profile');
        $this->assertSame('/users/profile', $request->path());

        $protocolRelative = new Request(method: 'GET', uri: '//example.com/users');
        $this->assertSame('/example.com/users', $protocolRelative->path());
    }

    #[Test]
    public function path_handles_dot_segments(): void
    {
        $request = new Request(method: 'GET', uri: '/users/../admin');
        $this->assertSame('/users/../admin', $request->path());
    }

    #[Test]
    public function chained_with_methods_produce_independent_instances(): void
    {
        $base = new Request(method: 'GET', uri: '/');

        $r1 = $base->withAttribute('a', 1);
        $r2 = $r1->withAttribute('b', 2);
        $r3 = $r2->withHeader('X-Test', 'value');

        $this->assertFalse($base->hasAttribute('a'));
        $this->assertTrue($r1->hasAttribute('a'));
        $this->assertFalse($r1->hasAttribute('b'));
        $this->assertTrue($r2->hasAttribute('a'));
        $this->assertTrue($r2->hasAttribute('b'));
        $this->assertNull($r2->header('X-Test'));
        $this->assertSame('value', $r3->header('X-Test'));
    }

    #[Test]
    public function query_returns_null_value_when_key_exists_with_null(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            query: ['key' => null],
        );

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
