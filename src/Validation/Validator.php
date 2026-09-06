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
     * Built-in rule names. These are protected from override via withRule()
     * so that security- and correctness-relevant built-in semantics cannot be
     * silently weakened by a name collision.
     */
    private const BUILT_IN_RULES = ['required', 'string', 'integer', 'email', 'min', 'max', 'in'];

    /**
     * @var array<string, RuleInterface|class-string>
     */
    private array $customRules = [];

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
     * Derive a new Validator with a custom rule registered under the given
     * name. The Validator is immutable with respect to configuration: the
     * original instance is never modified, and existing custom rules are
     * carried over to the derived instance.
     *
     * Built-in rule names cannot be overridden; attempting to do so throws.
     * Registering an already-known custom name also throws — rebind by
     * deriving from the base Validator instead.
     *
     * @param string                       $name Rule name usable in string
     *                                          rule arrays (must match
     *                                          [a-zA-Z_][a-zA-Z0-9_]*, the
     *                                          same grammar as rule parsing).
     * @param RuleInterface|class-string   $rule A rule instance, or the name of
     *                                          an instantiable RuleInterface
     *                                          implementation class resolved
     *                                          at validation time.
     */
    public function withRule(string $name, RuleInterface|string $rule): self
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Validation rule name "%s" is invalid. Names must match [a-zA-Z_][a-zA-Z0-9_]*.',
                $name
            ));
        }

        if (in_array($name, self::BUILT_IN_RULES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Validation rule "%s" is built in and cannot be overridden.',
                $name
            ));
        }

        if (isset($this->customRules[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Validation rule "%s" is already registered on this Validator.',
                $name
            ));
        }

        if ($rule instanceof RuleInterface) {
            $derived = new self();
            $derived->customRules = $this->customRules;
            $derived->customRules[$name] = $rule;

            return $derived;
        }

        // class-string: validate eagerly so developer errors fail fast at
        // composition time rather than at first validation.
        if (!class_exists($rule)) {
            throw new \InvalidArgumentException(sprintf(
                'Validation rule class "%s" does not exist.',
                $rule
            ));
        }

        if (!is_subclass_of($rule, RuleInterface::class)) {
            throw new \InvalidArgumentException(sprintf(
                'Validation rule class "%s" must implement %s.',
                $rule,
                RuleInterface::class
            ));
        }

        $derived = new self();
        $derived->customRules = $this->customRules;
        $derived->customRules[$name] = $rule;

        return $derived;
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

        if (isset($this->customRules[$name])) {
            $custom = $this->customRules[$name];

            return $custom instanceof RuleInterface ? $custom : new $custom();
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
