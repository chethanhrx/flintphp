<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class Min implements RuleInterface
{
    public function __construct(private readonly int|float $min)
    {
    }

    public function passes(string $field, mixed $value, array $data): bool
    {
        if (is_numeric($value)) {
            return $value >= $this->min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $this->min;
        }

        if (is_array($value)) {
            return count($value) >= $this->min;
        }

        return false;
    }

    public function message(string $field): string
    {
        return sprintf('The %s must be at least %s.', $field, $this->min);
    }
}
