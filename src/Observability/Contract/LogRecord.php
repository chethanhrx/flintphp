<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Contract;

/**
 * Immutable structured log entry.
 *
 * Each LogRecord captures a single log event with its level, message,
 * contextual data, channel, and timestamp.
 *
 * Context is stored as a plain PHP array. The logger does not serialize,
 * inspect, or sanitize context values. Callers are responsible for
 * ensuring that context does not contain secrets or unsafe data.
 */
final readonly class LogRecord
{
    /** @var array<string, mixed> */
    public array $context;

    public \DateTimeImmutable $timestamp;

    public function __construct(
        public LogLevel $level,
        public string $message,
        array $context = [],
        public string $channel = 'app',
        ?\DateTimeImmutable $timestamp = null,
    ) {
        $this->context = $context;
        $this->timestamp = $timestamp ?? new \DateTimeImmutable();
    }
}
