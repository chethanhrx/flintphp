<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Logging;

use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use FlintPHP\Framework\Observability\Contract\LogLevel;

/**
 * No-op logger that discards all log messages.
 *
 * Intended for disabled/no-op logging scenarios where log output
 * must be silently suppressed without altering application logic.
 */
final class NullLogger implements LoggerInterface
{
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function debug(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function info(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function notice(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function warning(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function error(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function critical(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function alert(string $message, array $context = []): void
    {
        // Intentionally empty.
    }

    public function emergency(string $message, array $context = []): void
    {
        // Intentionally empty.
    }
}
