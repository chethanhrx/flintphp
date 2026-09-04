<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class Email implements RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field): string
    {
        return sprintf('The %s must be a valid email address.', $field);
    }
}
