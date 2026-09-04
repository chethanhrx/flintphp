<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

/**
 * Contract for writing output to the console.
 */
interface OutputInterface
{
    /**
     * Write a message to standard output without a newline.
     */
    public function write(string $message): void;

    /**
     * Write a message to standard output with a newline.
     */
    public function writeLn(string $message): void;

    /**
     * Write a message to standard error without a newline.
     */
    public function writeError(string $message): void;

    /**
     * Write a message to standard error with a newline.
     */
    public function writeErrorLn(string $message): void;
}
