<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

use InvalidArgumentException;

/**
 * Standard implementation of console output using stream resources.
 */
final class ConsoleOutput implements OutputInterface
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private mixed $stdout = STDOUT,
        private mixed $stderr = STDERR,
    ) {
        if (!is_resource($this->stdout)) {
            throw new InvalidArgumentException('STDOUT must be a valid stream resource.');
        }

        if (!is_resource($this->stderr)) {
            throw new InvalidArgumentException('STDERR must be a valid stream resource.');
        }
    }

    public function write(string $message): void
    {
        fwrite($this->stdout, $message);
    }

    public function writeLn(string $message): void
    {
        $this->write($message . PHP_EOL);
    }

    public function writeError(string $message): void
    {
        fwrite($this->stderr, $message);
    }

    public function writeErrorLn(string $message): void
    {
        $this->writeError($message . PHP_EOL);
    }
}
