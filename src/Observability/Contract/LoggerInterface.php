<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Observability\Contract;

/**
 * Contract for structured logging.
 *
 * Implementations must delegate all convenience methods to log().
 */
interface LoggerInterface
{
    /**
     * Log a message at the given level.
     *
     * @param LogLevel $level   The severity level.
     * @param string   $message The log message. Stored verbatim; no interpolation or escaping is performed.
     * @param array    $context Arbitrary contextual data. Callers are responsible for avoiding
     *                          secrets and unsafe values — the logger does not inspect or sanitize context.
     */
    public function log(LogLevel $level, string $message, array $context = []): void;

    public function debug(string $message, array $context = []): void;

    public function info(string $message, array $context = []): void;

    public function notice(string $message, array $context = []): void;

    public function warning(string $message, array $context = []): void;

    public function error(string $message, array $context = []): void;

    public function critical(string $message, array $context = []): void;

    public function alert(string $message, array $context = []): void;

    public function emergency(string $message, array $context = []): void;
}
