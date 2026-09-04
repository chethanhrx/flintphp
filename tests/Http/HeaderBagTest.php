<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderBag::class)]
final class HeaderBagTest extends TestCase
{
    #[Test]
    public function it_can_be_created_empty(): void
    {
        $bag = new HeaderBag();

        $this->assertTrue($bag->isEmpty());
        $this->assertSame(0, $bag->count());
        $this->assertSame([], $bag->all());
    }

    #[Test]
    public function it_stores_and_retrieves_headers(): void
    {
        $bag = new HeaderBag([
            'Content-Type' => 'application/json',
            'Accept' => 'text/html',
        ]);

        $this->assertSame('application/json', $bag->get('Content-Type'));
        $this->assertSame('text/html', $bag->get('Accept'));
    }

    #[Test]
    public function it_retrieves_headers_case_insensitively(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'application/json']);

        $this->assertSame('application/json', $bag->get('content-type'));
        $this->assertSame('application/json', $bag->get('CONTENT-TYPE'));
        $this->assertSame('application/json', $bag->get('Content-Type'));
    }

    #[Test]
    public function it_checks_header_existence_case_insensitively(): void
    {
        $bag = new HeaderBag(['Authorization' => 'Bearer token']);

        $this->assertTrue($bag->has('Authorization'));
        $this->assertTrue($bag->has('authorization'));
        $this->assertTrue($bag->has('AUTHORIZATION'));
        $this->assertFalse($bag->has('X-Custom'));
    }

    #[Test]
    public function it_returns_null_for_missing_headers(): void
    {
        $bag = new HeaderBag(['Accept' => 'text/html']);

        $this->assertNull($bag->get('X-Missing'));
    }

    #[Test]
    public function it_handles_multiple_values_for_a_header(): void
    {
        $bag = new HeaderBag([
            'Accept' => ['text/html', 'application/json'],
        ]);

        // get() returns the first value
        $this->assertSame('text/html', $bag->get('Accept'));

        // getAll() returns all values
        $this->assertSame(['text/html', 'application/json'], $bag->getAll('Accept'));
    }

    #[Test]
    public function get_all_returns_empty_array_for_missing_header(): void
    {
        $bag = new HeaderBag();

        $this->assertSame([], $bag->getAll('X-Missing'));
    }

    #[Test]
    public function with_header_returns_new_instance(): void
    {
        $original = new HeaderBag(['Accept' => 'text/html']);
        $modified = $original->withHeader('Content-Type', 'application/json');

        // Original unchanged
        $this->assertNull($original->get('Content-Type'));
        $this->assertSame(1, $original->count());

        // New instance has the header
        $this->assertSame('application/json', $modified->get('Content-Type'));
        $this->assertSame(2, $modified->count());
    }

    #[Test]
    public function with_header_replaces_existing_header_case_insensitively(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'text/html']);
        $modified = $bag->withHeader('content-type', 'application/json');

        $this->assertSame('application/json', $modified->get('Content-Type'));
        $this->assertSame(1, $modified->count());
    }

    #[Test]
    public function without_header_returns_new_instance(): void
    {
        $original = new HeaderBag([
            'Content-Type' => 'text/html',
            'Accept' => 'text/html',
        ]);
        $modified = $original->withoutHeader('Content-Type');

        // Original unchanged
        $this->assertTrue($original->has('Content-Type'));
        $this->assertSame(2, $original->count());

        // New instance lacks the header
        $this->assertFalse($modified->has('Content-Type'));
        $this->assertSame(1, $modified->count());
    }

    #[Test]
    public function without_header_is_case_insensitive(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'text/html']);
        $modified = $bag->withoutHeader('content-type');

        $this->assertFalse($modified->has('Content-Type'));
    }

    #[Test]
    public function all_preserves_original_header_casing(): void
    {
        $bag = new HeaderBag([
            'Content-Type' => 'text/html',
            'X-Custom-Header' => 'value',
        ]);

        $all = $bag->all();

        $this->assertArrayHasKey('Content-Type', $all);
        $this->assertArrayHasKey('X-Custom-Header', $all);
    }

    #[Test]
    public function it_handles_string_values_as_single_element_arrays(): void
    {
        $bag = new HeaderBag(['Accept' => 'text/html']);

        $this->assertSame(['text/html'], $bag->getAll('Accept'));
    }

    #[Test]
    public function it_counts_headers_correctly(): void
    {
        $bag = new HeaderBag([
            'Content-Type' => 'text/html',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer token',
        ]);

        $this->assertSame(3, $bag->count());
        $this->assertFalse($bag->isEmpty());
    }
}
