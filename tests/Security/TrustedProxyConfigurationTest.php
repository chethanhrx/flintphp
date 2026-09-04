<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Security;

use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrustedProxyConfiguration::class)]
final class TrustedProxyConfigurationTest extends TestCase
{
    #[Test]
    public function it_identifies_trusted_proxies(): void
    {
        $config = new TrustedProxyConfiguration(['10.0.0.1', '192.168.1.5']);

        $this->assertTrue($config->isTrusted('10.0.0.1'));
        $this->assertTrue($config->isTrusted('192.168.1.5'));
        $this->assertFalse($config->isTrusted('10.0.0.2'));
        $this->assertFalse($config->isTrusted('127.0.0.1'));
    }

    #[Test]
    public function it_supports_ipv4_cidr(): void
    {
        $config = new TrustedProxyConfiguration(['10.0.0.0/24']);

        $this->assertTrue($config->isTrusted('10.0.0.5'));
        $this->assertTrue($config->isTrusted('10.0.0.255'));
        $this->assertFalse($config->isTrusted('10.0.1.5'));
    }

    #[Test]
    public function it_supports_ipv6_cidr(): void
    {
        $config = new TrustedProxyConfiguration(['2001:db8::/32']);

        $this->assertTrue($config->isTrusted('2001:db8::1'));
        $this->assertTrue($config->isTrusted('2001:db8:ffff:ffff:ffff:ffff:ffff:ffff'));
        $this->assertFalse($config->isTrusted('2001:db9::1'));
    }

    #[Test]
    public function it_supports_edge_case_cidrs(): void
    {
        $config0 = new TrustedProxyConfiguration(['10.0.0.0/0']);
        $this->assertTrue($config0->isTrusted('255.255.255.255'));
        $this->assertTrue($config0->isTrusted('1.1.1.1'));
        $this->assertFalse($config0->isTrusted('::1')); // different protocol

        $config32 = new TrustedProxyConfiguration(['10.0.0.1/32']);
        $this->assertTrue($config32->isTrusted('10.0.0.1'));
        $this->assertFalse($config32->isTrusted('10.0.0.2'));

        $config128 = new TrustedProxyConfiguration(['2001:db8::1/128']);
        $this->assertTrue($config128->isTrusted('2001:db8::1'));
        $this->assertFalse($config128->isTrusted('2001:db8::2'));
    }

    #[Test]
    public function it_rejects_malformed_cidrs_and_ips(): void
    {
        $config = new TrustedProxyConfiguration([
            '10.0.0.1/33', // invalid mask
            '10.0.0.1/-1', // invalid mask
            '2001:db8::1/129', // invalid mask
            'not_an_ip', // invalid network
            'not_an_ip/24', // invalid network
        ]);

        $this->assertFalse($config->isTrusted('10.0.0.1'));
        $this->assertFalse($config->isTrusted('2001:db8::1'));
        $this->assertFalse($config->isTrusted('not_an_ip'));
    }

    #[Test]
    public function it_handles_mismatched_protocols(): void
    {
        $config = new TrustedProxyConfiguration(['10.0.0.0/24']);
        $this->assertFalse($config->isTrusted('::1'));

        $config2 = new TrustedProxyConfiguration(['2001:db8::/32']);
        $this->assertFalse($config2->isTrusted('10.0.0.1'));
    }

    #[Test]
    public function it_defaults_to_no_trusted_proxies(): void
    {
        $config = new TrustedProxyConfiguration();

        $this->assertFalse($config->isTrusted('10.0.0.1'));
        $this->assertFalse($config->isTrusted('127.0.0.1'));
    }
}
