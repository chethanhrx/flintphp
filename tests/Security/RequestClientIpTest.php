<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Security;

use FlintPHP\Framework\Http\Request;
use FlintPHP\Framework\Security\Http\TrustedProxyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Request::class)]
final class RequestClientIpTest extends TestCase
{
    private function simulateFromGlobals(array $server, ?TrustedProxyConfiguration $trustedProxies = null): Request
    {
        // Temporarily override $_SERVER just for the test
        $originalServer = $_SERVER;
        $_SERVER = $server;
        
        $request = Request::fromGlobals($trustedProxies);
        
        $_SERVER = $originalServer;
        
        return $request;
    }

    #[Test]
    public function it_resolves_remote_addr_when_no_proxies_trusted(): void
    {
        $server = [
            'REMOTE_ADDR' => '1.2.3.4',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9'
        ];

        $request = $this->simulateFromGlobals($server);
        
        $this->assertSame('1.2.3.4', $request->clientIp());
    }

    #[Test]
    public function it_resolves_remote_addr_when_proxy_is_untrusted(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9'
        ];

        $trusted = new TrustedProxyConfiguration(['10.0.0.1']);
        $request = $this->simulateFromGlobals($server, $trusted);
        
        $this->assertSame('10.0.0.2', $request->clientIp());
    }

    #[Test]
    public function it_resolves_forwarded_for_when_proxy_is_trusted(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9'
        ];

        $trusted = new TrustedProxyConfiguration(['10.0.0.1']);
        $request = $this->simulateFromGlobals($server, $trusted);
        
        $this->assertSame('9.9.9.9', $request->clientIp());
    }

    #[Test]
    public function it_gets_rightmost_untrusted_ip_in_chain(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.1',
            // The request went through 8.8.8.8 (client), then 10.0.0.2 (untrusted), then 10.0.0.1 (trusted)
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 10.0.0.2'
        ];

        $trusted = new TrustedProxyConfiguration(['10.0.0.1']);
        $request = $this->simulateFromGlobals($server, $trusted);
        
        // 10.0.0.2 is untrusted, so it's identified as the client
        $this->assertSame('10.0.0.2', $request->clientIp());
    }

    #[Test]
    public function it_gets_leftmost_ip_when_all_proxies_trusted(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.1',
            // Client is 8.8.8.8, hitting trusted proxy 10.0.0.2, hitting trusted proxy 10.0.0.1
            'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 10.0.0.2'
        ];

        $trusted = new TrustedProxyConfiguration(['10.0.0.1', '10.0.0.2']);
        $request = $this->simulateFromGlobals($server, $trusted);
        
        $this->assertSame('8.8.8.8', $request->clientIp());
    }

    #[Test]
    public function it_handles_missing_remote_addr(): void
    {
        $server = [];
        $request = $this->simulateFromGlobals($server);
        
        $this->assertNull($request->clientIp());
    }
}
