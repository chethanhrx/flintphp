<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Exception;

/**
 * Thrown when a text payload or close reason contains invalid UTF-8 sequences.
 */
final class InvalidUtf8Exception extends WebSocketException
{
}
