<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Handshake;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use FlintPHP\Framework\WebSocket\Handshake\OriginPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OriginPolicy::class)]
final class OriginPolicyTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_allowlisted_origins_and_normalizes_scheme_host_and_default_port(): void
    {
        $policy = new OriginPolicy([
            'https://example.com',
            'http://localhost:3000',
            'https://127.0.0.1',
            'https://[::1]',
        ]);

        $policy->validate($this->request('https://EXAMPLE.COM:443'));
        $policy->validate($this->request('http://localhost:3000'));
        $policy->validate($this->request('https://127.0.0.1:443'));
        $policy->validate($this->request('https://[0:0:0:0:0:0:0:1]'));
        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_an_origin_not_in_the_allowlist(): void
    {
        $this->expectException(HandshakeException::class);
        (new OriginPolicy(['https://example.com']))->validate($this->request('https://attacker.example'));
    }

    #[Test]
    public function it_rejects_missing_origin_by_default_and_can_explicitly_allow_it(): void
    {
        try {
            (new OriginPolicy(['https://example.com']))->validate($this->request(null));
            $this->fail('Missing Origin must be rejected by default.');
        } catch (HandshakeException) {
            $this->assertTrue(true);
        }

        (new OriginPolicy(['https://example.com'], allowMissingOrigin: true))->validate($this->request(null));
        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_multiple_origin_headers(): void
    {
        $request = new Request('GET', '/ws', [
            'Origin' => ['https://example.com', 'https://attacker.example'],
        ]);

        $this->expectException(HandshakeException::class);
        (new OriginPolicy(['https://example.com']))->validate($request);
    }

    #[Test]
    public function it_rejects_malformed_hosts_ipv6_and_ports(): void
    {
        $policy = new OriginPolicy(['https://example.com']);
        foreach ([
            'https://example..com',
            'https://.example.com',
            'https://example.com.',
            'https://-bad.example',
            'https://bad-.example',
            'https://a..b',
            'https://[::::]',
            'https://[gggg::1]',
            'https://127.0.0.999',
            'https://example.com:',
            'https://example.com:0',
            'https://example.com:65536',
            'https://example.com:999999999999',
            'https://example.com:notaport',
        ] as $origin) {
            try {
                $policy->validate($this->request($origin));
                $this->fail('Expected malformed origin to be rejected: ' . $origin);
            } catch (HandshakeException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_rejects_paths_credentials_queries_fragments_and_non_http_schemes(): void
    {
        $policy = new OriginPolicy(['https://example.com']);
        foreach ([
            'https://example.com/path',
            'https://user@example.com',
            'https://user:pass@example.com',
            'https://example.com?x=1',
            'https://example.com#fragment',
            'javascript://example.com',
            ' https://example.com',
            'https://example.com ',
        ] as $origin) {
            try {
                $policy->validate($this->request($origin));
                $this->fail('Expected origin to be rejected: ' . $origin);
            } catch (HandshakeException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_rejects_empty_or_malformed_configured_allowlists(): void
    {
        try {
            new OriginPolicy([]);
            $this->fail('Expected empty allowlist to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        foreach (['https://example.com/path', 'https://example..com', 'https://[::::]'] as $origin) {
            try {
                new OriginPolicy([$origin]);
                $this->fail('Expected invalid configured origin to be rejected: ' . $origin);
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    private function request(?string $origin): Request
    {
        return new Request('GET', '/ws', $origin === null ? [] : ['Origin' => $origin]);
    }
}
