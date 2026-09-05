<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Exception;

/**
 * Thrown when an RFC 6455 protocol violation occurs.
 * Examples: bad opcodes, invalid RSV bits, non-minimal lengths, invalid masking.
 */
final class ProtocolException extends WebSocketException
{
}
