<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation;

final class ValidationResult
{
    /**
     * @param array<string, array<int, string>> $errors
     * @param array<string, mixed> $validated
     */
    public function __construct(
        private readonly array $errors = [],
        private readonly array $validated = []
    ) {
    }

    /**
     * Determine if the data passed validation.
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the validated data.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validated;
    }
}
