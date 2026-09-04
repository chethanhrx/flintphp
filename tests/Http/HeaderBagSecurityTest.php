<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Http;

use FlintPHP\Framework\Http\HeaderBag;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HeaderBagSecurityTest extends TestCase
{
    #[Test]
    public function it_rejects_header_name_with_newline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(["Header\nName" => 'value']);
    }

    #[Test]
    public function it_rejects_header_name_with_carriage_return(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(["Header\rName" => 'value']);
    }

    #[Test]
    public function it_rejects_header_name_with_colon(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(["Header:Name" => 'value']);
    }

    #[Test]
    public function it_rejects_header_name_with_space(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(["Header Name" => 'value']);
    }

    #[Test]
    public function it_rejects_empty_header_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(["" => 'value']);
    }

    #[Test]
    public function it_rejects_value_with_carriage_return(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(['X-Header' => "value\rvalue"]);
    }

    #[Test]
    public function it_rejects_value_with_newline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HeaderBag(['X-Header' => "value\nvalue"]);
    }

    #[Test]
    public function it_rejects_malformed_values_in_with_header(): void
    {
        $bag = new HeaderBag();
        $this->expectException(InvalidArgumentException::class);
        $bag->withHeader('X-Header', "evil\r\nvalue");
    }

    #[Test]
    public function it_rejects_malformed_names_in_with_header(): void
    {
        $bag = new HeaderBag();
        $this->expectException(InvalidArgumentException::class);
        $bag->withHeader("X-Header\r\n", 'value');
    }

    #[Test]
    public function it_accepts_valid_headers(): void
    {
        $bag = new HeaderBag([
            'X-Frame-Options' => 'DENY',
            'Accept-Encoding' => ['gzip', 'deflate'],
            'custom_header' => 'value123',
            'Header!#$%&\'*+-.^_`|~' => 'valid-rfc-7230-token'
        ]);

        $this->assertTrue($bag->has('X-Frame-Options'));
        $this->assertSame(['gzip', 'deflate'], $bag->getAll('Accept-Encoding'));
    }
}
