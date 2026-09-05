<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Testing;

use FlintPHP\Framework\Http\Response;
use PHPUnit\Framework\Assert;

/**
 * A fluent wrapper for testing HTTP responses.
 */
final class TestResponse
{
    public function __construct(
        public readonly Response $baseResponse,
    ) {
    }

    /**
     * Assert that the response has the given status code.
     */
    public function assertStatus(int $status): self
    {
        Assert::assertSame(
            $status,
            $this->baseResponse->status(),
            sprintf('Expected response status code %d but received %d.', $status, $this->baseResponse->status())
        );

        return $this;
    }

    /**
     * Assert that the response has a 200 OK status code.
     */
    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    /**
     * Assert that the response has a 404 Not Found status code.
     */
    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    /**
     * Assert that the response contains the given header and value.
     */
    public function assertHeader(string $name, string $value): self
    {
        $actual = $this->baseResponse->header($name);

        Assert::assertNotNull(
            $actual,
            sprintf('Header "%s" is not present on response.', $name)
        );

        Assert::assertSame(
            $value,
            $actual,
            sprintf('Header "%s" was found, but value "%s" does not match expected "%s".', $name, $actual, $value)
        );

        return $this;
    }

    /**
     * Assert that the response body is exactly the given string.
     */
    public function assertBody(string $expected): self
    {
        Assert::assertSame($expected, $this->baseResponse->body());

        return $this;
    }

    /**
     * Assert that the response body is JSON and decodes to the expected array.
     */
    public function assertJson(array $expected): self
    {
        $body = $this->baseResponse->body();
        
        try {
            $actual = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Assert::fail('Response body is not valid JSON: ' . $e->getMessage());
        }

        Assert::assertSame($expected, $actual);

        return $this;
    }
}
