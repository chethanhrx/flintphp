<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

/**
 * Contract for input objects that understand command-line options in
 * addition to positional arguments.
 *
 * Extends the positional-only InputInterface, so ParsedInput is accepted
 * anywhere an InputInterface is expected (backward compatibility).
 *
 * Grammar (deterministic, documented):
 *   - The command name is the first token after the script name, verbatim.
 *   - "--name=value" sets a valued option. Duplicate names: last one wins.
 *   - "--name" (no "=") records a boolean flag.
 *   - "--" ends option parsing; every token after it is positional, even
 *     if it looks like an option.
 *   - A single leading dash ("-v") is NOT an option; it stays positional
 *     (consistent with the legacy positional-only Input behavior).
 *   - Option NAMES must match [a-zA-Z0-9][a-zA-Z0-9_-]*; anything else is a
 *     developer error and throws InvalidArgumentException.
 *   - Option VALUES and positional arguments are opaque data. They are
 *     never interpreted, expanded, or executed. Shell metacharacters,
 *     control characters, and path fragments are preserved verbatim.
 */
interface ParsedInputInterface extends InputInterface
{
    /**
     * Whether the option was provided (as a flag or with a value).
     */
    public function hasOption(string $name): bool;

    /**
     * The value of a valued option (--name=value), or null when the option
     * is absent OR provided as a bare flag. Combine with hasOption() to
     * distinguish "flag" from "absent".
     */
    public function option(string $name): ?string;

    /**
     * All valued options keyed by name (last occurrence wins).
     *
     * @return array<string, string>
     */
    public function options(): array;

    /**
     * Names of the boolean flags that were provided, first occurrence wins.
     *
     * @return list<string>
     */
    public function flags(): array;
}
