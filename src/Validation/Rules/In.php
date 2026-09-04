<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation\Rules;

use FlintPHP\Framework\Validation\RuleInterface;

final class In implements RuleInterface
{
    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public function passes(string $field, mixed $value, array $data): bool
    {
        return in_array($value, $this->values, true);
    }

    public function message(string $field): string
    {
        return sprintf('The selected %s is invalid.', $field);
    }
}
