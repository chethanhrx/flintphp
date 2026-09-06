<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Security\Http;

final class TrustedProxyConfiguration
{
    /**
     * @param string[] $trustedProxies
     */
    public function __construct(
        private readonly array $trustedProxies = []
    ) {
    }

    /**
     * Check if the given IP address is in the list of trusted proxies.
     * Supports exact IPs and CIDR ranges (IPv4 and IPv6).
     *
     * @param string $ip
     * @return bool
     */
    public function isTrusted(string $ip): bool
    {
        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return false;
        }

        foreach ($this->trustedProxies as $trustedProxy) {
            if (str_contains($trustedProxy, '/')) {
                [$net, $mask] = explode('/', $trustedProxy, 2);
                if (!preg_match('/^\d+$/', $mask)) {
                    continue;
                }
                $mask = (int) $mask;
            } else {
                $net = $trustedProxy;
                $mask = null; // Will be set based on IP version
            }

            $netBinary = @inet_pton($net);
            if ($netBinary === false) {
                continue;
            }

            // Ensure both are same protocol (IPv4 is 4 bytes, IPv6 is 16 bytes)
            if (strlen($ipBinary) !== strlen($netBinary)) {
                continue;
            }

            if ($mask === null) {
                $mask = strlen($ipBinary) === 4 ? 32 : 128;
            }

            // Handle edge case where mask is 0 (matches any IP of the same protocol)
            if ($mask === 0) {
                return true;
            }
            
            if ($mask < 0 || ($mask > 32 && strlen($ipBinary) === 4) || $mask > 128) {
                continue; // Malformed CIDR
            }

            // Compare binary strings bit by bit
            $bytes = (int) ($mask / 8);
            $bits = $mask % 8;

            if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($netBinary, 0, $bytes)) {
                continue;
            }

            if ($bits > 0) {
                $ipByte = ord($ipBinary[$bytes]);
                $netByte = ord($netBinary[$bytes]);
                $bitmask = (0xFF << (8 - $bits)) & 0xFF;

                if (($ipByte & $bitmask) !== ($netByte & $bitmask)) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }
}
