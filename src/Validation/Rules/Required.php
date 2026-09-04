<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class Required implements RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }

    public function message(string $field): string
    {
        return sprintf('The %s field is required.', $field);
    }
}
