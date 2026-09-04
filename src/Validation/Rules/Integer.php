<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class Integer implements RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return true;
        }

        return false;
    }

    public function message(string $field): string
    {
        return sprintf('The %s must be an integer.', $field);
    }
}
