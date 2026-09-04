<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\Method;
use FlintPHP\Framework\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    // ---------------------------------------------------------------
    // Construction
    // ---------------------------------------------------------------

    #[Test]
    public function it_can_be_constructed_with_minimal_arguments(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame('GET', $request->method());
        $this->assertSame('/', $request->uri());
    }

    #[Test]
    public function it_stores_all_constructor_arguments(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/users?page=2',
            headers: ['Content-Type' => 'application/json'],
            body: '{"name":"test"}',
            server: ['SERVER_NAME' => 'localhost'],
            cookies: ['session' => 'abc123'],
            query: ['page' => '2'],
        );

        $this->assertSame('POST', $request->method());
        $this->assertSame('/users?page=2', $request->uri());
        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('{"name":"test"}', $request->body());
        $this->assertSame('localhost', $request->server('SERVER_NAME'));
        $this->assertSame('abc123', $request->cookie('session'));
        $this->assertSame('2', $request->query('page'));
    }

    #[Test]
    public function it_accepts_header_bag_in_constructor(): void
    {
        $bag = new HeaderBag(['Accept' => 'text/html']);
        $request = new Request(method: 'GET', uri: '/', headers: $bag);

        $this->assertSame('text/html', $request->header('Accept'));
    }

    // ---------------------------------------------------------------
    // HTTP method
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_http_method(): void
    {
        $request = new Request(method: 'DELETE', uri: '/');

        $this->assertSame('DELETE', $request->method());
    }

    #[Test]
    public function http_method_returns_typed_enum_for_standard_methods(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame(Method::GET, $request->httpMethod());
    }

    #[Test]
    public function http_method_returns_null_for_non_standard_methods(): void
    {
        $request = new Request(method: 'PURGE', uri: '/');

        $this->assertNull($request->httpMethod());
    }

    #[Test]
    public function is_method_compares_case_insensitively(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertTrue($request->isMethod('GET'));
        $this->assertTrue($request->isMethod('get'));
        $this->assertTrue($request->isMethod('Get'));
        $this->assertFalse($request->isMethod('POST'));
    }

    // ---------------------------------------------------------------
    // URI and path
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_full_uri(): void
    {
        $request = new Request(method: 'GET', uri: '/users/42?active=true');

        $this->assertSame('/users/42?active=true', $request->uri());
    }

    #[Test]
    public function it_extracts_path_from_uri(): void
    {
        $request = new Request(method: 'GET', uri: '/users/42?active=true');

        $this->assertSame('/users/42', $request->path());
    }

    #[Test]
    public function path_returns_root_for_root_uri(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame('/', $request->path());
    }

    #[Test]
    public function path_returns_path_without_query_string(): void
    {
        $request = new Request(method: 'GET', uri: '/users?page=2');

        $this->assertSame('/users', $request->path());
    }

    #[Test]
    public function path_handles_uri_with_only_query_string(): void
    {
        $request = new Request(method: 'GET', uri: '/?search=test');

        $this->assertSame('/', $request->path());
    }

    #[Test]
    public function path_handles_encoded_uri_components(): void
    {
        $request = new Request(method: 'GET', uri: '/users/hello%20world');

        $this->assertSame('/users/hello%20world', $request->path());
    }

    #[Test]
    public function path_handles_uri_with_fragment(): void
    {
        $request = new Request(method: 'GET', uri: '/page#section');

        $this->assertSame('/page', $request->path());
    }

    // ---------------------------------------------------------------
    // Query parameters
    // ---------------------------------------------------------------

    #[Test]
    public function query_returns_all_parameters_when_called_without_key(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users?page=2&limit=10',
            query: ['page' => '2', 'limit' => '10'],
        );

        $this->assertSame(['page' => '2', 'limit' => '10'], $request->query());
    }

    #[Test]
    public function query_returns_single_parameter_by_key(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/users?page=2',
            query: ['page' => '2'],
        );

        $this->assertSame('2', $request->query('page'));
    }

    #[Test]
    public function query_returns_default_for_missing_key(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->query('missing'));
        $this->assertSame('default', $request->query('missing', 'default'));
    }

    #[Test]
    public function query_returns_empty_array_when_no_parameters(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame([], $request->query());
    }

    #[Test]
    public function query_handles_nested_parameters(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/search?filter[status]=active',
            query: ['filter' => ['status' => 'active']],
        );

        $this->assertSame(['status' => 'active'], $request->query('filter'));
    }

    // ---------------------------------------------------------------
    // Headers
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_header_bag(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: ['Accept' => 'text/html'],
        );

        $this->assertInstanceOf(HeaderBag::class, $request->headers());
    }

    #[Test]
    public function header_shortcut_returns_value_case_insensitively(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            headers: ['Content-Type' => 'application/json'],
        );

        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('application/json', $request->header('CONTENT-TYPE'));
    }

    #[Test]
    public function header_returns_null_for_missing_header(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->header('X-Missing'));
    }

    // ---------------------------------------------------------------
    // Body
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_request_body(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/',
            body: '{"key":"value"}',
        );

        $this->assertSame('{"key":"value"}', $request->body());
    }

    #[Test]
    public function body_defaults_to_empty_string(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame('', $request->body());
    }

    // ---------------------------------------------------------------
    // Server parameters
    // ---------------------------------------------------------------

    #[Test]
    public function server_returns_all_parameters_when_called_without_key(): void
    {
        $server = ['SERVER_NAME' => 'localhost', 'SERVER_PORT' => '8080'];
        $request = new Request(method: 'GET', uri: '/', server: $server);

        $this->assertSame($server, $request->server());
    }

    #[Test]
    public function server_returns_single_parameter_by_key(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            server: ['SERVER_NAME' => 'localhost'],
        );

        $this->assertSame('localhost', $request->server('SERVER_NAME'));
    }

    #[Test]
    public function server_returns_default_for_missing_key(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->server('MISSING'));
        $this->assertSame('fallback', $request->server('MISSING', 'fallback'));
    }

    // ---------------------------------------------------------------
    // Cookies
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_all_cookies(): void
    {
        $cookies = ['session' => 'abc', 'theme' => 'dark'];
        $request = new Request(method: 'GET', uri: '/', cookies: $cookies);

        $this->assertSame($cookies, $request->cookies());
    }

    #[Test]
    public function cookie_returns_single_value(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/',
            cookies: ['session' => 'abc123'],
        );

        $this->assertSame('abc123', $request->cookie('session'));
    }

    #[Test]
    public function cookie_returns_null_for_missing_cookie(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->cookie('missing'));
    }

    // ---------------------------------------------------------------
    // fromGlobals factory
    // ---------------------------------------------------------------

    #[Test]
    public function from_globals_creates_request_from_superglobals(): void
    {
        // Back up superglobals
        $origServer = $_SERVER;
        $origGet = $_GET;
        $origCookie = $_COOKIE;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/users?active=1',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CUSTOM' => 'custom-value',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '42',
                'SERVER_NAME' => 'example.com',
            ];
            $_GET = ['active' => '1'];
            $_COOKIE = ['token' => 'xyz'];

            $request = Request::fromGlobals();

            $this->assertSame('POST', $request->method());
            $this->assertSame('/api/users?active=1', $request->uri());
            $this->assertSame('/api/users', $request->path());
            $this->assertSame('1', $request->query('active'));
            $this->assertSame('application/json', $request->header('Accept'));
            $this->assertSame('custom-value', $request->header('X-Custom'));
            $this->assertSame('application/json', $request->header('Content-Type'));
            $this->assertSame('42', $request->header('Content-Length'));
            $this->assertSame('xyz', $request->cookie('token'));
            $this->assertSame('example.com', $request->server('SERVER_NAME'));
        } finally {
            // Restore superglobals
            $_SERVER = $origServer;
            $_GET = $origGet;
            $_COOKIE = $origCookie;
        }
    }

    #[Test]
    public function from_globals_defaults_to_get_when_method_is_missing(): void
    {
        $origServer = $_SERVER;
        $origGet = $_GET;
        $origCookie = $_COOKIE;

        try {
            $_SERVER = ['REQUEST_URI' => '/'];
            $_GET = [];
            $_COOKIE = [];

            $request = Request::fromGlobals();

            $this->assertSame('GET', $request->method());
        } finally {
            $_SERVER = $origServer;
            $_GET = $origGet;
            $_COOKIE = $origCookie;
        }
    }

    #[Test]
    public function from_globals_defaults_to_root_when_uri_is_missing(): void
    {
        $origServer = $_SERVER;
        $origGet = $_GET;
        $origCookie = $_COOKIE;

        try {
            $_SERVER = ['REQUEST_METHOD' => 'GET'];
            $_GET = [];
            $_COOKIE = [];

            $request = Request::fromGlobals();

            $this->assertSame('/', $request->uri());
            $this->assertSame('/', $request->path());
        } finally {
            $_SERVER = $origServer;
            $_GET = $origGet;
            $_COOKIE = $origCookie;
        }
    }

    #[Test]
    public function from_globals_uppercases_method(): void
    {
        $origServer = $_SERVER;
        $origGet = $_GET;
        $origCookie = $_COOKIE;

        try {
            $_SERVER = ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/'];
            $_GET = [];
            $_COOKIE = [];

            $request = Request::fromGlobals();

            $this->assertSame('POST', $request->method());
        } finally {
            $_SERVER = $origServer;
            $_GET = $origGet;
            $_COOKIE = $origCookie;
        }
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function it_handles_unusual_but_valid_http_methods(): void
    {
        $request = new Request(method: 'PROPFIND', uri: '/');

        $this->assertSame('PROPFIND', $request->method());
        $this->assertNull($request->httpMethod());
        $this->assertTrue($request->isMethod('PROPFIND'));
    }

    #[Test]
    public function it_handles_empty_body(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame('', $request->body());
    }

    #[Test]
    public function it_handles_empty_cookies(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame([], $request->cookies());
    }

    #[Test]
    public function it_handles_empty_server_parameters(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame([], $request->server());
    }
}
