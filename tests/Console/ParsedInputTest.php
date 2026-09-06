<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Console;

use FlintPHP\Framework\Console\Input;
use FlintPHP\Framework\Console\ParsedInput;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParsedInput::class)]
final class ParsedInputTest extends TestCase
{
    #[Test]
    public function it_parses_command_name_positionals_options_and_flags(): void
    {
        $input = new ParsedInput([
            'bin/flint',
            'make:controller',
            'UserController',
            '--resource',
            '--env=production',
        ]);

        $this->assertSame('make:controller', $input->getCommandName());
        $this->assertSame('UserController', $input->getArgument(0));
        // Legacy positional semantics (pinned by InputTest): ALL tokens after
        // the command name are positional, even option-looking ones. Option
        // classification is an ADDITIONAL view, never a replacement.
        $this->assertSame('--resource', $input->getArgument(1));
        $this->assertSame('--env=production', $input->getArgument(2));
        $this->assertSame(['UserController', '--resource', '--env=production'], $input->getArguments());
        $this->assertTrue($input->hasOption('resource'));
        $this->assertNull($input->option('resource')); // flag, not valued
        $this->assertSame(['resource'], $input->flags());
        $this->assertTrue($input->hasOption('env'));
        $this->assertSame('production', $input->option('env'));
        $this->assertSame(['env' => 'production'], $input->options());
    }

    #[Test]
    public function positional_accessors_remain_identical_to_legacy_input(): void
    {
        $argv = ['bin/flint', 'cmd', 'a', '--flag', '--k=v', '', 'x y'];
        $legacy = new Input($argv);
        $parsed = new ParsedInput($argv);

        $this->assertSame($legacy->getCommandName(), $parsed->getCommandName());
        $this->assertSame($legacy->getArgument(0), $parsed->getArgument(0));
        $this->assertSame($legacy->getArgument(1), $parsed->getArgument(1));
        $this->assertSame($legacy->getArguments(), $parsed->getArguments());
    }

    #[Test]
    public function last_duplicate_option_wins(): void
    {
        $input = new ParsedInput([
            'bin/flint', 'cmd', '--env=staging', '--env=production',
        ]);

        $this->assertSame('production', $input->option('env'));
        $this->assertSame(['env' => 'production'], $input->options());
    }

    #[Test]
    public function duplicate_flags_are_recorded_once(): void
    {
        $input = new ParsedInput([
            'bin/flint', 'cmd', '--force', '--force',
        ]);

        $this->assertSame(['force'], $input->flags());
        $this->assertTrue($input->hasOption('force'));
    }

    #[Test]
    public function flag_then_valued_option_and_valued_then_flag_both_resolved_deterministically(): void
    {
        $a = new ParsedInput(['bin/flint', 'cmd', '--name', '--name=value']);
        $b = new ParsedInput(['bin/flint', 'cmd', '--name=value', '--name']);

        // A valued occurrence always yields the value.
        $this->assertTrue($a->hasOption('name'));
        $this->assertSame('value', $a->option('name'));
        // A later bare flag does not erase an earlier value.
        $this->assertSame('value', $b->option('name'));
        $this->assertFalse(in_array('name', $b->flags(), true) && $b->option('name') === null);
    }

    #[Test]
    public function double_dash_separator_ends_option_parsing(): void
    {
        $input = new ParsedInput([
            'bin/flint', 'cmd', '--real', '--', '--not-an-option', '--k=v',
        ]);

        $this->assertTrue($input->hasOption('real'));
        $this->assertFalse($input->hasOption('not-an-option'));
        $this->assertFalse($input->hasOption('k'));
        // After "--", everything is positional — and the classified option
        // token and the separator itself both remain in the raw positional
        // view (legacy semantics: every token after the command name).
        $this->assertSame(['--real', '--', '--not-an-option', '--k=v'], $input->getArguments());
    }

    #[Test]
    public function single_dash_tokens_stay_positional(): void
    {
        $input = new ParsedInput([
            'bin/flint', 'cmd', '-v', '-x=1',
        ]);

        $this->assertFalse($input->hasOption('v'));
        $this->assertFalse($input->hasOption('x'));
        $this->assertSame(['-v', '-x=1'], $input->getArguments());
    }

    #[Test]
    public function bare_double_dash_is_rejected_as_option_but_works_as_separator(): void
    {
        // "--" alone is the separator, never an option.
        $input = new ParsedInput(['bin/flint', 'cmd', '--', '--']);

        $this->assertSame([], $input->flags());
        $this->assertSame([], $input->options());
    }

    #[Test]
    public function malformed_option_names_fail_loudly(): void
    {
        foreach (['--=value', '--a b=v', '--a;b=v', '--é=v'] as $token) {
            try {
                new ParsedInput(['bin/flint', 'cmd', $token]);
                $this->fail(sprintf('Malformed option "%s" was accepted.', $token));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Malformed option name', $e->getMessage());
            }
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function empty_option_name_after_double_dash_is_a_clear_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed option');

        new ParsedInput(['bin/flint', 'cmd', '---']);
    }

    #[Test]
    public function empty_option_values_are_preserved(): void
    {
        $input = new ParsedInput(['bin/flint', 'cmd', '--env=']);

        $this->assertTrue($input->hasOption('env'));
        $this->assertSame('', $input->option('env'));
    }

    #[Test]
    public function security_torture_values_are_treated_as_pure_data(): void
    {
        $hostile = [
            '; rm -rf /',
            '$(whoami)',
            '`whoami`',
            '../../../../etc/passwd',
            "line1\nline2",
            "cr\rinjected",
            "nul\0byte",
            'unicode-é-ü-ñ-日本語',
        ];

        foreach ($hostile as $i => $value) {
            $input = new ParsedInput(['bin/flint', 'cmd', "--arg={$value}", $value]);

            $this->assertSame($value, $input->option('arg'), "Option value mutated for case {$i}");
            // Raw token view is also preserved verbatim (legacy semantics:
            // the option token itself is positional index 0, the value is 1).
            $this->assertSame("--arg={$value}", $input->getArgument(0), "Raw token mutated for case {$i}");
            $this->assertSame($value, $input->getArgument(1), "Positional argument mutated for case {$i}");
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function very_long_values_are_preserved_verbatim(): void
    {
        $long = str_repeat('A', 65536);

        $input = new ParsedInput(['bin/flint', 'cmd', "--blob={$long}"]);

        $this->assertSame($long, $input->option('blob'));
    }

    #[Test]
    public function repeated_executions_return_identical_results(): void
    {
        $build = fn (): ParsedInput => new ParsedInput(['bin/flint', 'cmd', 'a', '--x=1', '--y']);

        $first = $build();
        $second = $build();

        $this->assertSame($first->options(), $second->options());
        $this->assertSame($first->flags(), $second->flags());
        $this->assertSame($first->getArguments(), $second->getArguments());
    }
}
