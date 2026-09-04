<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class Max implements RuleInterface
{
    public function __construct(private readonly int|float $max)
    {
    }

    public function passes(string $field, mixed $value, array $data): bool
    {
        if (is_numeric($value)) {
            return $value <= $this->max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $this->max;
        }

        if (is_array($value)) {
            return count($value) <= $this->max;
        }

        return false;
    }

    public function message(string $field): string
    {
        return sprintf('The %s must not be greater than %s.', $field, $this->max);
    }
}
