<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Message;

/**
 * Represents a completely assembled, immutable WebSocket message.
 */
final readonly class Message
{
    public function __construct(
        public string $payload,
        public bool $text,
    ) {}
}
