<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\Method;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Method::class)]
final class MethodTest extends TestCase
{
    #[Test]
    public function it_has_all_standard_http_methods(): void
    {
        $expected = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        $actual = array_map(fn(Method $m) => $m->value, Method::cases());

        $this->assertSame($expected, $actual);
    }

    #[Test]
    #[DataProvider('standardMethodProvider')]
    public function it_can_be_created_from_string(string $value, Method $expected): void
    {
        $this->assertSame($expected, Method::from($value));
    }

    #[Test]
    public function try_from_returns_null_for_unknown_method(): void
    {
        $this->assertNull(Method::tryFrom('PURGE'));
        $this->assertNull(Method::tryFrom('PROPFIND'));
        $this->assertNull(Method::tryFrom(''));
    }

    #[Test]
    public function try_from_is_case_sensitive(): void
    {
        // PHP backed enums are case-sensitive by design
        $this->assertNull(Method::tryFrom('get'));
        $this->assertNull(Method::tryFrom('Get'));
        $this->assertSame(Method::GET, Method::tryFrom('GET'));
    }

    /**
     * @return iterable<string, array{string, Method}>
     */
    public static function standardMethodProvider(): iterable
    {
        yield 'GET' => ['GET', Method::GET];
        yield 'POST' => ['POST', Method::POST];
        yield 'PUT' => ['PUT', Method::PUT];
        yield 'PATCH' => ['PATCH', Method::PATCH];
        yield 'DELETE' => ['DELETE', Method::DELETE];
        yield 'HEAD' => ['HEAD', Method::HEAD];
        yield 'OPTIONS' => ['OPTIONS', Method::OPTIONS];
    }
}
