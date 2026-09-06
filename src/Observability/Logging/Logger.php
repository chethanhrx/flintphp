<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Logging;

use FlintPHP\Framework\Observability\Contract\LoggerInterface;
use FlintPHP\Framework\Observability\Contract\LogLevel;
use FlintPHP\Framework\Observability\Contract\LogRecord;
use FlintPHP\Framework\Observability\Exception\ObservabilityException;

/**
 * In-memory structured logger.
 *
 * Stores LogRecord objects produced by calls to log(). Performs no I/O.
 *
 * This logger is intended as a foundational building block. Future versions
 * may introduce I/O adapters (file, database, HTTP) that consume these records.
 *
 * Frame parsing may temporarily allocate additional memory for record storage;
 * the caller is responsible for clearing records when they are no longer needed.
 */
final class Logger implements LoggerInterface
{
    private const CHANNEL_PATTERN = '/^[a-zA-Z0-9_.\-]{1,64}\z/';

    /** @var list<LogRecord> */
    private array $records = [];

    private readonly string $channel;

    /**
     * @throws ObservabilityException If the channel name is invalid.
     */
    public function __construct(string $channel = 'app')
    {
        if (preg_match(self::CHANNEL_PATTERN, $channel) !== 1) {
            throw new ObservabilityException(
                sprintf('Invalid logger channel "%s". Channel must match pattern %s.', $channel, self::CHANNEL_PATTERN)
            );
        }

        $this->channel = $channel;
    }

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->records[] = new LogRecord(
            level: $level,
            message: $message,
            context: $context,
            channel: $this->channel,
        );
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

    /**
     * Return all stored log records.
     *
     * Returns a value-isolated copy. Modifying the returned array
     * does not affect the logger's internal state.
     *
     * @return list<LogRecord>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * Clear all stored log records for this logger instance.
     */
    public function clear(): void
    {
        $this->records = [];
    }
}
