<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Console;

use InvalidArgumentException;

/**
 * Option-aware console input.
 *
 * Deterministic, allocation-light parsing of argv into a command name,
 * positional arguments, valued options, and boolean flags. There is no shell
 * interpretation here: PHP has already split argv into tokens; this class
 * only classifies and validates tokens. Argument and option values are
 * treated as opaque data end to end.
 *
 * Options require the double-dash form. Values must use "--name=value".
 * Bare "--name" is a boolean flag. The "--" token ends option parsing.
 * Single-dash tokens ("-v") remain positional, matching the behavior of the
 * legacy positional-only Input (pinned by tests).
 *
 * Positional accessors (getCommandName/getArgument/getArguments) are
 * byte-for-byte semantically identical to Input: every token after the
 * script name is positional, even if it looks like an option. This class is
 * a strict extension of the CLI grammar, never a behavior change.
 */
final class ParsedInput implements ParsedInputInterface
{
    /**
     * All tokens after the script name, in order.
     *
     * @var array<int, string>
     */
    private array $arguments;

    /**
     * @var array<string, string> valued options, last occurrence wins
     */
    private array $options = [];

    /**
     * @var list<string> boolean flags, first occurrence wins
     */
    private array $flagNames = [];

    /**
     * @param array<int, string> $argv raw argv array (index 0 = script name)
     *
     * @throws InvalidArgumentException If an option name is malformed.
     */
    public function __construct(array $argv)
    {
        // $argv[0] is the script name (e.g. 'bin/flint'); positional
        // semantics match the legacy Input exactly.
        $this->arguments = array_values(array_slice($argv, 1));

        $tokens = $argv;
        array_shift($tokens); // the script name is never classified
        $this->parseTokens($tokens);
    }

    public function getCommandName(): ?string
    {
        return $this->arguments[0] ?? null;
    }

    public function getArgument(int $index): ?string
    {
        // Shift by 1 to skip the command name itself.
        return $this->arguments[$index + 1] ?? null;
    }

    public function getArguments(): array
    {
        return array_values(array_slice($this->arguments, 1));
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]) || in_array($name, $this->flagNames, true);
    }

    public function option(string $name): ?string
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return list<string>
     */
    public function flags(): array
    {
        return $this->flagNames;
    }

    /**
     * Classify tokens after the script name.
     *
     * @param list<string> $tokens
     *
     * @throws InvalidArgumentException If an option name is malformed.
     */
    private function parseTokens(array $tokens): void
    {
        // The first token (if any) is the command name — always verbatim,
        // never classified as an option.
        array_shift($tokens);

        $inOptions = true;

        foreach ($tokens as $token) {
            if ($inOptions && $token === '--') {
                $inOptions = false;
                continue;
            }

            if ($inOptions && str_starts_with($token, '--')) {
                $this->parseOption(substr($token, 2));
                continue;
            }

            // Positional argument (or single-dash token): opaque data.
        }
    }

    /**
     * Parse a single "--..." option token.
     *
     * @throws InvalidArgumentException If the option name is malformed.
     */
    private function parseOption(string $body): void
    {
        if ($body === '') {
            throw new InvalidArgumentException(
                'Malformed option "--": use "--" to end option parsing or "--name=value" to pass an option.'
            );
        }

        $name = $body;
        $value = null;

        if (str_contains($body, '=')) {
            [$name, $value] = explode('=', $body, 2);
        }

        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Malformed option name "%s". Names must match [a-zA-Z0-9][a-zA-Z0-9_-]*.',
                $name
            ));
        }

        if ($value === null) {
            if (!in_array($name, $this->flagNames, true)) {
                $this->flagNames[] = $name;
            }

            return;
        }

        // Last occurrence wins: deterministic and documented.
        $this->options[$name] = $value;
    }
}
