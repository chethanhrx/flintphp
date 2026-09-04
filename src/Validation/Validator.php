<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Validation;

use FlintPHP\Framework\Validation\Rules\Email;
use FlintPHP\Framework\Validation\Rules\In;
use FlintPHP\Framework\Validation\Rules\Integer;
use FlintPHP\Framework\Validation\Rules\Max;
use FlintPHP\Framework\Validation\Rules\Min;
use FlintPHP\Framework\Validation\Rules\Required;
use FlintPHP\Framework\Validation\Rules\StringRule;
use RuntimeException;

final class Validator
{
    /**
     * Validate the given data against the provided rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<int, string|RuleInterface>> $rules
     */
    public function validate(array $data, array $rules): ValidationResult
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $isEmpty = $this->isEmpty($value);

            // Determine if the 'required' rule is present for this field.
            $isRequired = $this->hasRequiredRule($fieldRules);

            // If the field is empty and not required, we skip further validation for it.
            if ($isEmpty && !$isRequired) {
                continue;
            }

            foreach ($fieldRules as $rule) {
                $ruleObj = $this->resolveRule($rule);

                if (!$ruleObj->passes($field, $value, $data)) {
                    $errors[$field][] = $ruleObj->message($field);
                }
            }

            if (!isset($errors[$field]) && array_key_exists($field, $data)) {
                $validated[$field] = $data[$field];
            }
        }

        return new ValidationResult($errors, $validated);
    }

    /**
     * Determine if a value is considered "empty" in FlintPHP.
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * Check if the 'required' rule exists in the array of rules.
     *
     * @param array<int, string|RuleInterface> $rules
     */
    private function hasRequiredRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule === 'required' || $rule instanceof Required) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a string rule or return the RuleInterface directly.
     */
    private function resolveRule(string|RuleInterface $rule): RuleInterface
    {
        if ($rule instanceof RuleInterface) {
            return $rule;
        }

        $segments = explode(':', $rule, 2);
        $name = $segments[0];
        $parameters = isset($segments[1]) ? explode(',', $segments[1]) : [];

        if ($name === 'min' || $name === 'max') {
            if (!isset($parameters[0]) || $parameters[0] === '') {
                throw new RuntimeException(sprintf('Validation rule "%s" requires a parameter.', $name));
            }
            if (!is_numeric($parameters[0])) {
                throw new RuntimeException(sprintf('Validation rule "%s" requires a numeric parameter.', $name));
            }
        }

        return match ($name) {
            'required' => new Required(),
            'string'   => new StringRule(),
            'integer'  => new Integer(),
            'email'    => new Email(),
            'min'      => new Min((float) $parameters[0]),
            'max'      => new Max((float) $parameters[0]),
            'in'       => new In($parameters),
            default    => throw new RuntimeException(sprintf('Validation rule "%s" is not supported.', $name)),
        };
    }
}
