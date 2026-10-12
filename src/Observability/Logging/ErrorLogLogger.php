<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Logging;

use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use FlintPHP\Framework\Observability\Contract\LogLevel;

/**
 * Minimal production logger: JSON lines via PHP error_log().
 *
 * No secrets should be placed in $context by callers. Does not read
 * request headers or bodies.
 */
final class ErrorLogLogger implements LoggerInterface
{
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $payload = [
            'level' => $level->value,
            'message' => $message,
            'context' => $context,
            'time' => gmdate('c'),
        ];

        error_log(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $message);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }
}
