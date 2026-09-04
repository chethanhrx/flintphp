<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Security\Middleware;

final class SecurityHeadersConfiguration
{
    public function __construct(
        public readonly string $xContentTypeOptions = 'nosniff',
        public readonly string $xFrameOptions = 'DENY',
        public readonly string $referrerPolicy = 'strict-origin-when-cross-origin',
        public readonly ?string $contentSecurityPolicy = null,
        public readonly ?string $strictTransportSecurity = null
    ) {
    }

    public function withContentSecurityPolicy(string $policy): self
    {
        return new self(
            $this->xContentTypeOptions,
            $this->xFrameOptions,
            $this->referrerPolicy,
            $policy,
            $this->strictTransportSecurity
        );
    }

    public function withStrictTransportSecurity(int $maxAge, bool $includeSubDomains = false, bool $preload = false): self
    {
        $header = 'max-age=' . $maxAge;
        if ($includeSubDomains) {
            $header .= '; includeSubDomains';
        }
        if ($preload) {
            $header .= '; preload';
        }

        return new self(
            $this->xContentTypeOptions,
            $this->xFrameOptions,
            $this->referrerPolicy,
            $this->contentSecurityPolicy,
            $header
        );
    }
}
