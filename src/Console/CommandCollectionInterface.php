<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

/**
 * Read-only view over the commands registered in a ConsoleApplication.
 *
 * Exists so the built-in list/help commands can present registered commands
 * without gaining write access to the registry. Implemented by
 * ConsoleApplication; no global registry is involved.
 */
interface CommandCollectionInterface
{
    /**
     * All registered commands, in registration order, keyed by name.
     *
     * @return array<string, CommandInterface>
     */
    public function commands(): array;

    /**
     * Look up a single registered command by exact name.
     */
    public function command(string $name): ?CommandInterface;
}
