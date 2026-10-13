<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\WebSocket\Handshake;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\WebSocket\Exception\HandshakeException;
use FlintPHP\Framework\WebSocket\Handshake\OriginPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

#[CoversClass(OriginPolicy::class)]
final class OriginPolicyTest extends TestCase
{
    #[Test]
    public function it_accepts_an_allowlisted_origin_and_normalizes_scheme_host_and_default_port(): void
    {
        $policy = new OriginPolicy(['https://example.com', 'http://localhost:3000']);
        $policy->validate($this->request('https://EXAMPLE.COM:443'));
        $policy->validate($this->request('http://localhost:3000'));
        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_an_origin_not_in_the_allowlist(): void
    {
        $this->expectException(HandshakeException::class);
        (new OriginPolicy(['https://example.com']))->validate($this->request('https://attacker.example'));
    }

    #[Test]
    public function it_rejects_missing_origin_by_default(): void
    {
        $this->expectException(HandshakeException::class);
        (new OriginPolicy(['https://example.com']))->validate($this->request(null));
    }

    #[Test]
    public function it_can_explicitly_allow_missing_origin_for_non_browser_clients(): void
    {
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
    public function it_rejects_origins_with_paths_credentials_or_non_http_schemes(): void
    {
        $policy = new OriginPolicy(['https://example.com']);
        foreach ([
            'https://example.com/path',
            'https://user@example.com',
            'javascript://example.com',
            'https://example.com?x=1',
            ' https://example.com',
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
    public function it_rejects_empty_allowlists_and_malformed_configured_origins(): void
    {
        try {
            new OriginPolicy([]);
            $this->fail('Expected empty allowlist to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        new OriginPolicy(['https://example.com/path']);
    }

    private function request(?string $origin): Request
    {
        return new Request('GET', '/ws', $origin === null ? [] : ['Origin' => $origin]);
    }
}
