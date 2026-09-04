<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Console;

use FlintPHP\Framework\Console\Input;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Input::class)]
final class InputTest extends TestCase
{
    #[Test]
    public function it_extracts_command_name_and_arguments(): void
    {
        // Simulate: php bin/console make:controller UserController
        $argv = [
            'bin/console',
            'make:controller',
            'UserController',
        ];

        $input = new Input($argv);

        $this->assertSame('make:controller', $input->getCommandName());
        $this->assertSame('UserController', $input->getArgument(0));
        $this->assertNull($input->getArgument(1));
        $this->assertSame(['UserController'], $input->getArguments());
    }

    #[Test]
    public function it_handles_missing_command_name(): void
    {
        $argv = [
            'bin/console',
        ];

        $input = new Input($argv);

        $this->assertNull($input->getCommandName());
        $this->assertNull($input->getArgument(0));
        $this->assertSame([], $input->getArguments());
    }

    #[Test]
    public function it_treats_all_values_as_positional_arguments_even_if_they_look_like_options(): void
    {
        $argv = [
            'bin/console',
            'make:controller',
            'UserController',
            '--force',
            '-v',
            '--name=value',
            '',
            'arbitrary string'
        ];

        $input = new Input($argv);

        $this->assertSame('make:controller', $input->getCommandName());
        $this->assertSame('UserController', $input->getArgument(0));
        $this->assertSame('--force', $input->getArgument(1));
        $this->assertSame('-v', $input->getArgument(2));
        $this->assertSame('--name=value', $input->getArgument(3));
        $this->assertSame('', $input->getArgument(4));
        $this->assertSame('arbitrary string', $input->getArgument(5));
        $this->assertNull($input->getArgument(6));

        $expected = [
            'UserController',
            '--force',
            '-v',
            '--name=value',
            '',
            'arbitrary string'
        ];
        $this->assertSame($expected, $input->getArguments());
    }
}
