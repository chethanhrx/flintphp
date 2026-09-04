<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use FlintPHP\Framework\Http\HttpException;
use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    // ---------------------------------------------------------------
    // Construction and defaults
    // ---------------------------------------------------------------

    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $response = new Response();

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
        $this->assertTrue($response->headers()->isEmpty());
    }

    #[Test]
    public function it_can_be_constructed_with_body_and_status(): void
    {
        $response = new Response(body: 'Hello FlintPHP', status: 201);

        $this->assertSame(201, $response->status());
        $this->assertSame('Hello FlintPHP', $response->body());
    }

    #[Test]
    public function it_accepts_headers_in_constructor(): void
    {
        $response = new Response(
            body: 'OK',
            headers: ['Content-Type' => 'text/plain'],
        );

        $this->assertSame('text/plain', $response->header('Content-Type'));
    }

    #[Test]
    public function it_accepts_header_bag_in_constructor(): void
    {
        $bag = new HeaderBag(['X-Custom' => 'value']);
        $response = new Response(body: '', headers: $bag);

        $this->assertSame('value', $response->header('X-Custom'));
    }

    // ---------------------------------------------------------------
    // Status code
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_status_code(): void
    {
        $response = new Response(status: 404);

        $this->assertSame(404, $response->status());
    }

    #[Test]
    #[DataProvider('validStatusCodeProvider')]
    public function it_accepts_valid_status_codes(int $code): void
    {
        $response = new Response(status: $code);

        $this->assertSame($code, $response->status());
    }

    #[Test]
    #[DataProvider('invalidStatusCodeProvider')]
    public function it_rejects_invalid_status_codes(int $code): void
    {
        $this->expectException(HttpException::class);

        new Response(status: $code);
    }

    // ---------------------------------------------------------------
    // Body
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_body(): void
    {
        $response = new Response(body: '<h1>Hello</h1>');

        $this->assertSame('<h1>Hello</h1>', $response->body());
    }

    // ---------------------------------------------------------------
    // Headers
    // ---------------------------------------------------------------

    #[Test]
    public function it_returns_the_header_bag(): void
    {
        $response = new Response(headers: ['Accept' => 'text/html']);

        $this->assertInstanceOf(HeaderBag::class, $response->headers());
    }

    #[Test]
    public function header_shortcut_is_case_insensitive(): void
    {
        $response = new Response(headers: ['Content-Type' => 'text/html']);

        $this->assertSame('text/html', $response->header('content-type'));
        $this->assertSame('text/html', $response->header('CONTENT-TYPE'));
    }

    #[Test]
    public function header_returns_null_for_missing_header(): void
    {
        $response = new Response();

        $this->assertNull($response->header('X-Missing'));
    }

    // ---------------------------------------------------------------
    // Immutable mutations (withX)
    // ---------------------------------------------------------------

    #[Test]
    public function with_status_returns_new_instance(): void
    {
        $original = new Response(status: 200);
        $modified = $original->withStatus(404);

        $this->assertSame(200, $original->status());
        $this->assertSame(404, $modified->status());
        $this->assertNotSame($original, $modified);
    }

    #[Test]
    public function with_status_validates_code(): void
    {
        $response = new Response();

        $this->expectException(HttpException::class);

        $response->withStatus(999);
    }

    #[Test]
    public function with_header_returns_new_instance(): void
    {
        $original = new Response();
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertNull($original->header('X-Custom'));
        $this->assertSame('value', $modified->header('X-Custom'));
        $this->assertNotSame($original, $modified);
    }

    #[Test]
    public function with_body_returns_new_instance(): void
    {
        $original = new Response(body: 'original');
        $modified = $original->withBody('modified');

        $this->assertSame('original', $original->body());
        $this->assertSame('modified', $modified->body());
        $this->assertNotSame($original, $modified);
    }

    #[Test]
    public function chained_with_methods_work_correctly(): void
    {
        $response = (new Response())
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody('{"created":true}');

        $this->assertSame(201, $response->status());
        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame('{"created":true}', $response->body());
    }

    #[Test]
    public function with_header_preserves_other_state(): void
    {
        $original = new Response(body: 'test', status: 201);
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertSame('test', $modified->body());
        $this->assertSame(201, $modified->status());
    }

    // ---------------------------------------------------------------
    // JSON response
    // ---------------------------------------------------------------

    #[Test]
    public function json_creates_json_response(): void
    {
        $response = Response::json(['message' => 'Hello']);

        $this->assertSame('{"message":"Hello"}', $response->body());
        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame(200, $response->status());
    }

    #[Test]
    public function json_accepts_custom_status_code(): void
    {
        $response = Response::json(['created' => true], status: 201);

        $this->assertSame(201, $response->status());
    }

    #[Test]
    public function json_handles_unicode_correctly(): void
    {
        $response = Response::json(['name' => '日本語']);

        // JSON_UNESCAPED_UNICODE is a default flag
        $this->assertSame('{"name":"日本語"}', $response->body());
    }

    #[Test]
    public function json_handles_nested_data(): void
    {
        $data = [
            'user' => [
                'id' => 1,
                'roles' => ['admin', 'user'],
            ],
        ];

        $response = Response::json($data);

        $decoded = json_decode($response->body(), true);
        $this->assertSame($data, $decoded);
    }

    #[Test]
    public function json_handles_scalar_values(): void
    {
        $this->assertSame('"hello"', Response::json('hello')->body());
        $this->assertSame('42', Response::json(42)->body());
        $this->assertSame('true', Response::json(true)->body());
        $this->assertSame('null', Response::json(null)->body());
    }

    #[Test]
    public function json_throws_on_encoding_failure(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to encode response data as JSON');

        // NAN cannot be JSON encoded
        Response::json(NAN);
    }

    #[Test]
    public function json_accepts_additional_encoding_flags(): void
    {
        $response = Response::json(
            ['url' => 'https://example.com/path'],
            flags: JSON_UNESCAPED_SLASHES,
        );

        $this->assertStringContainsString('https://example.com/path', $response->body());
    }

    #[Test]
    public function json_accepts_extra_headers(): void
    {
        $response = Response::json(
            ['ok' => true],
            headers: ['X-Request-Id' => 'abc-123'],
        );

        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame('abc-123', $response->header('X-Request-Id'));
    }

    // ---------------------------------------------------------------
    // Data providers
    // ---------------------------------------------------------------

    /**
     * @return iterable<string, array{int}>
     */
    public static function validStatusCodeProvider(): iterable
    {
        yield 'minimum valid' => [100];
        yield 'OK' => [200];
        yield 'created' => [201];
        yield 'redirect' => [301];
        yield 'not found' => [404];
        yield 'server error' => [500];
        yield 'maximum valid' => [599];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidStatusCodeProvider(): iterable
    {
        yield 'below minimum' => [99];
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above maximum' => [600];
        yield 'far above maximum' => [999];
    }
}
