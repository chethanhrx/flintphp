<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Testing;

use FlintPHP\Framework\Http\Response;
use FlintPHP\Framework\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestResponse::class)]
final class TestResponseTest extends TestCase
{
    #[Test]
    public function it_asserts_status_code(): void
    {
        $response = new Response('ok', 201);
        $testResponse = new TestResponse($response);

        $testResponse->assertStatus(201);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Expected response status code 200 but received 201.');
        $testResponse->assertStatus(200);
    }

    #[Test]
    public function it_asserts_ok(): void
    {
        $response = new Response('ok', 200);
        $testResponse = new TestResponse($response);

        $testResponse->assertOk();

        $response404 = new Response('not found', 404);
        $testResponse404 = new TestResponse($response404);

        $this->expectException(ExpectationFailedException::class);
        $testResponse404->assertOk();
    }

    #[Test]
    public function it_asserts_not_found(): void
    {
        $response = new Response('not found', 404);
        $testResponse = new TestResponse($response);

        $testResponse->assertNotFound();

        $response200 = new Response('ok', 200);
        $testResponse200 = new TestResponse($response200);

        $this->expectException(ExpectationFailedException::class);
        $testResponse200->assertNotFound();
    }

    #[Test]
    public function it_asserts_header(): void
    {
        $response = (new Response('ok'))->withHeader('X-Custom', 'TestValue');
        $testResponse = new TestResponse($response);

        $testResponse->assertHeader('X-Custom', 'TestValue');

        // Test missing header
        try {
            $testResponse->assertHeader('X-Missing', 'Value');
            $this->fail('Expected exception for missing header.');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Header "X-Missing" is not present', $e->getMessage());
        }

        // Test wrong value
        try {
            $testResponse->assertHeader('X-Custom', 'WrongValue');
            $this->fail('Expected exception for wrong header value.');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('does not match expected "WrongValue"', $e->getMessage());
        }
    }

    #[Test]
    public function it_asserts_body(): void
    {
        $response = new Response('Hello World');
        $testResponse = new TestResponse($response);

        $testResponse->assertBody('Hello World');

        $this->expectException(ExpectationFailedException::class);
        $testResponse->assertBody('Wrong Body');
    }

    #[Test]
    public function it_asserts_json(): void
    {
        $response = new Response('{"name":"Alice","age":30}');
        $testResponse = new TestResponse($response);

        $testResponse->assertJson(['name' => 'Alice', 'age' => 30]);

        // Test non-json body
        $badResponse = new Response('Not JSON');
        $badTestResponse = new TestResponse($badResponse);

        try {
            $badTestResponse->assertJson(['name' => 'Alice']);
            $this->fail('Expected exception for invalid JSON.');
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('Response body is not valid JSON: Syntax error', $e->getMessage());
        }

        // Test mismatched JSON
        try {
            $testResponse->assertJson(['name' => 'Bob']);
            $this->fail('Expected exception for mismatched JSON.');
        } catch (ExpectationFailedException $e) {
            // PHPUnit handles the diff
        }
    }
}
