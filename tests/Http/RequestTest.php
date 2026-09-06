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
    // ---------------------------------------------------------------
    // Attributes
    // ---------------------------------------------------------------

    #[Test]
    public function it_has_no_attributes_by_default(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame([], $request->attributes());
        $this->assertFalse($request->hasAttribute('missing'));
    }

    #[Test]
    public function it_can_set_and_get_a_string_attribute(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $withAttr = $request->withAttribute('test', 'value');

        $this->assertTrue($withAttr->hasAttribute('test'));
        $this->assertSame('value', $withAttr->attribute('test'));
    }

    #[Test]
    public function attribute_returns_null_for_missing_key(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertNull($request->attribute('missing'));
    }

    #[Test]
    public function attribute_returns_default_for_missing_key(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $this->assertSame('fallback', $request->attribute('missing', 'fallback'));
    }

    #[Test]
    public function existing_null_attribute_overrides_default(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $request = $request->withAttribute('foo', null);

        $this->assertTrue($request->hasAttribute('foo'));
        $this->assertNull($request->attribute('foo', 'fallback'));
    }

    #[Test]
    public function it_preserves_falsey_values(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $r1 = $request->withAttribute('bool', false);
        $this->assertTrue($r1->hasAttribute('bool'));
        $this->assertFalse($r1->attribute('bool', true));

        $r2 = $request->withAttribute('int', 0);
        $this->assertTrue($r2->hasAttribute('int'));
        $this->assertSame(0, $r2->attribute('int', 99));

        $r3 = $request->withAttribute('float', 0.0);
        $this->assertTrue($r3->hasAttribute('float'));
        $this->assertSame(0.0, $r3->attribute('float', 99.9));

        $r4 = $request->withAttribute('string', '');
        $this->assertTrue($r4->hasAttribute('string'));
        $this->assertSame('', $r4->attribute('string', 'fallback'));

        $r5 = $request->withAttribute('array', []);
        $this->assertTrue($r5->hasAttribute('array'));
        $this->assertSame([], $r5->attribute('array', ['fallback']));
    }

    #[Test]
    public function it_preserves_object_identity(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $object = new \stdClass();
        $object->foo = 'bar';

        $withObject = $request->withAttribute('obj', $object);

        $this->assertSame($object, $withObject->attribute('obj'));
    }

    #[Test]
    public function it_stores_closures_without_execution(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $executed = false;
        $closure = function () use (&$executed) {
            $executed = true;
            return 'result';
        };

        $withClosure = $request->withAttribute('func', $closure);

        $this->assertSame($closure, $withClosure->attribute('func'));
        $this->assertFalse($executed);
    }

    #[Test]
    public function it_allows_arbitrary_keys(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        $request = $request->withAttribute('', 'empty_string');
        $this->assertSame('empty_string', $request->attribute(''));

        $request = $request->withAttribute('user', 'user_val');
        $this->assertSame('user_val', $request->attribute('user'));

        $request = $request->withAttribute('trace.id', 'trace_val');
        $this->assertSame('trace_val', $request->attribute('trace.id'));

        $request = $request->withAttribute('  ', 'whitespace');
        $this->assertSame('whitespace', $request->attribute('  '));

        $request = $request->withAttribute('ñ', 'unicode');
        $this->assertSame('unicode', $request->attribute('ñ'));
    }

    #[Test]
    public function keys_are_case_sensitive(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $request = $request->withAttribute('user', 'lower');
        $request = $request->withAttribute('User', 'camel');
        $request = $request->withAttribute('USER', 'upper');

        $this->assertSame('lower', $request->attribute('user'));
        $this->assertSame('camel', $request->attribute('User'));
        $this->assertSame('upper', $request->attribute('USER'));
    }

    #[Test]
    public function with_attribute_is_immutable(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $request2 = $request->withAttribute('foo', 'bar');

        $this->assertFalse($request->hasAttribute('foo'));
        $this->assertTrue($request2->hasAttribute('foo'));
        $this->assertNotSame($request, $request2);
    }

    #[Test]
    public function with_attribute_replaces_existing_key(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $r1 = $request->withAttribute('foo', 'one');
        $r2 = $r1->withAttribute('foo', 'two');

        $this->assertSame('one', $r1->attribute('foo'));
        $this->assertSame('two', $r2->attribute('foo'));
    }

    #[Test]
    public function multiple_attributes_survive_updates(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $r1 = $request->withAttribute('a', 1)->withAttribute('b', 2);

        $this->assertSame(1, $r1->attribute('a'));
        $this->assertSame(2, $r1->attribute('b'));

        $r2 = $r1->withAttribute('a', 99);
        $this->assertSame(99, $r2->attribute('a'));
        $this->assertSame(2, $r2->attribute('b'));
        $this->assertSame(1, $r1->attribute('a'));
    }

    #[Test]
    public function without_attribute_removes_key_immutably(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $r1 = $request->withAttribute('a', 1)->withAttribute('b', 2);

        $r2 = $r1->withoutAttribute('a');

        $this->assertFalse($r2->hasAttribute('a'));
        $this->assertTrue($r2->hasAttribute('b'));

        $this->assertTrue($r1->hasAttribute('a'));
        $this->assertNotSame($r1, $r2);
    }

    #[Test]
    public function without_attribute_on_missing_key_returns_clone_or_same(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $r2 = $request->withoutAttribute('missing');

        $this->assertFalse($r2->hasAttribute('missing'));
    }

    #[Test]
    public function attributes_returns_snapshot(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $r1 = $request->withAttribute('foo', 'bar');

        $attributes = $r1->attributes();
        $attributes['foo'] = 'mutated';
        $attributes['new'] = 'val';

        $this->assertSame('bar', $r1->attribute('foo'));
        $this->assertFalse($r1->hasAttribute('new'));
    }

    #[Test]
    public function attributes_returns_all_keys(): void
    {
        $request = new Request(method: 'GET', uri: '/');
        $r1 = $request->withAttribute('a', 1)->withAttribute('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], $r1->attributes());
    }

    #[Test]
    public function with_attribute_preserves_all_request_properties(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/users?page=2',
            headers: ['Content-Type' => 'application/json'],
            body: '{"name":"test"}',
            server: ['SERVER_NAME' => 'localhost'],
            cookies: ['session' => 'abc123'],
            query: ['page' => '2'],
            attributes: [],
            clientIp: '127.0.0.1'
        );

        $r2 = $request->withAttribute('foo', 'bar');

        $this->assertSame('POST', $r2->method());
        $this->assertSame('/users?page=2', $r2->uri());
        $this->assertSame('application/json', $r2->header('Content-Type'));
        $this->assertSame('{"name":"test"}', $r2->body());
        $this->assertSame('localhost', $r2->server('SERVER_NAME'));
        $this->assertSame('abc123', $r2->cookie('session'));
        $this->assertSame('2', $r2->query('page'));
        $this->assertSame('127.0.0.1', $r2->clientIp());
    }

    #[Test]
    public function from_globals_initializes_empty_attributes(): void
    {
        $origServer = $_SERVER;
        $origGet = $_GET;
        $origCookie = $_COOKIE;

        try {
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
            $_GET = [];
            $_COOKIE = [];

            $request = Request::fromGlobals();
            $this->assertSame([], $request->attributes());
        } finally {
            $_SERVER = $origServer;
            $_GET = $origGet;
            $_COOKIE = $origCookie;
        }
    }

    #[Test]
    public function multiple_requests_are_isolated(): void
    {
        $r1 = new Request(method: 'GET', uri: '/');
        $r2 = new Request(method: 'GET', uri: '/');

        $r1 = $r1->withAttribute('foo', 'r1');
        $r2 = $r2->withAttribute('foo', 'r2');

        $this->assertSame('r1', $r1->attribute('foo'));
        $this->assertSame('r2', $r2->attribute('foo'));
    }

    #[Test]
    public function get_attribute_is_deprecated_alias_for_attribute(): void
    {
        $request = new Request(method: 'GET', uri: '/');

        // This simulates legacy getAttribute calls
        $r1 = $request->withAttribute('user', 'legacy');

        $this->assertSame('legacy', $r1->getAttribute('user'));
        $this->assertNull($r1->getAttribute('missing'));
        $this->assertSame('fallback', $r1->getAttribute('missing', 'fallback'));
    }
}
