<?php

declare(strict_types=1);

namespace FlintPHP\Framework\WebSocket\Exception;

/**
 * Thrown when a WebSocket payload size, buffer size, or fragment count limit is exceeded.
 */
final class LimitExceededException extends WebSocketException
{
}
