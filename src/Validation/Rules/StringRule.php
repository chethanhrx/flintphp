<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class StringRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool
    {
        return is_string($value);
    }

    public function message(string $field): string
    {
        return sprintf('The %s must be a string.', $field);
    }
}
