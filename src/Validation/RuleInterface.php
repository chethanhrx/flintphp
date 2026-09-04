<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation;

interface RuleInterface
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $field The name of the field under validation.
     * @param mixed  $value The value of the field.
     * @param array<string, mixed> $data The entire dataset being validated.
     */
    public function passes(string $field, mixed $value, array $data): bool;

    /**
     * Get the validation error message.
     *
     * @param string $field The name of the field under validation.
     */
    public function message(string $field): string;
}
