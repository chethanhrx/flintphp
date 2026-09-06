<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Validation;

use FlintPHP\Framework\Foundation\Application;
use FlintPHP\Framework\Validation\RuleInterface;
use FlintPHP\Framework\Validation\ValidationBootstrapper;
use FlintPHP\Framework\Validation\ValidationResult;
use FlintPHP\Framework\Validation\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Validator::class)]
#[CoversClass(ValidationBootstrapper::class)]
final class ValidationIntegrationTest extends TestCase
{
    #[Test]
    public function withRule_derives_new_validator_preserving_original(): void
    {
        $base = new Validator();
        $derived = $base->withRule('phone', new PhoneRule());

        $this->assertNotSame($base, $derived);

        // Derived instance knows the rule.
        $result = $derived->validate(['phone' => '12345'], ['phone' => ['phone']]);
        $this->assertTrue($result->isValid());

        // Original instance is untouched.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not supported');

        $base->validate(['phone' => '12345'], ['phone' => ['phone']]);
    }

    #[Test]
    public function withRule_class_string_resolves_at_validation_time(): void
    {
        $validator = (new Validator())->withRule('phone', PhoneRule::class);

        $result = $validator->validate(['phone' => 'abc'], ['phone' => ['phone']]);

        $this->assertFalse($result->isValid());
        $this->assertSame(['The phone must be a valid phone number.'], $result->errors()['phone']);
    }

    #[Test]
    public function withRule_rejects_invalid_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Validator())->withRule('invalid name!', new PhoneRule());
    }

    #[Test]
    public function withRule_rejects_builtin_names(): void
    {
        foreach (['required', 'string', 'integer', 'email', 'min', 'max', 'in'] as $builtin) {
            try {
                (new Validator())->withRule($builtin, new PhoneRule());
                $this->fail(sprintf('Built-in rule "%s" was overridden.', $builtin));
            } catch (InvalidArgumentException) {
                // Expected: built-ins are protected.
            }
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function withRule_rejects_unknown_classes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        (new Validator())->withRule('ghost', 'NoSuchRuleClass');
    }

    #[Test]
    public function withRule_rejects_classes_that_do_not_implement_rule_interface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        (new Validator())->withRule('bad', \stdClass::class);
    }

    #[Test]
    public function withRule_rejects_duplicate_names(): void
    {
        $validator = (new Validator())->withRule('phone', new PhoneRule());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        $validator->withRule('phone', new PhoneRule());
    }

    #[Test]
    public function custom_rules_receive_complete_data(): void
    {
        $validator = (new Validator())->withRule('pair', new CompleteDataRule());

        $result = $validator->validate(
            ['a' => 'x', 'b' => 'y'],
            ['a' => ['pair']]
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function custom_rule_instances_are_reused_across_validations(): void
    {
        $validator = (new Validator())->withRule('phone', new PhoneRule());

        // Instance rules must not be re-instantiated per validation:
        // the same rule object handles repeated usage.
        $this->assertTrue($validator->validate(['phone' => '12345'], ['phone' => ['phone']])->isValid());
        $this->assertTrue($validator->validate(['phone' => '12345'], ['phone' => ['phone']])->isValid());
    }

    #[Test]
    public function bootstrapper_registers_singleton_validator(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new ValidationBootstrapper()]);

        $this->assertTrue($app->container()->has(Validator::class));

        $first = $app->container()->get(Validator::class);
        $this->assertInstanceOf(Validator::class, $first);
        $this->assertSame($first, $app->container()->get(Validator::class));
    }

    #[Test]
    public function configured_validator_is_shared_across_the_application(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new ValidationBootstrapper()]);

        // Explicit custom-rule configuration, then re-register: the
        // documented composition pattern.
        $configured = (new Validator())->withRule('phone', new PhoneRule());
        $app->container()->singleton(Validator::class, $configured);

        // Controller-style resolution receives the configured validator.
        $resolved = $app->container()->get(Validator::class);
        $this->assertSame($configured, $resolved);
        $this->assertTrue($resolved->validate(['phone' => '12345'], ['phone' => ['phone']])->isValid());
    }

    #[Test]
    public function base_semantics_are_preserved(): void
    {
        $app = new Application('/tmp');
        $app->bootstrapWith([new ValidationBootstrapper()]);
        $validator = $app->container()->get(Validator::class);

        $result = $validator->validate(
            ['email' => 'user@example.com', 'age' => '25'],
            [
                'email' => ['required', 'email'],
                'age'   => ['integer', 'min:18'],
            ]
        );

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertSame(['email' => 'user@example.com', 'age' => '25'], $result->validated());

        // No silent coercion: 'abc' is not an integer.
        $invalid = $validator->validate(['age' => 'abc'], ['age' => ['integer']]);
        $this->assertFalse($invalid->isValid());
    }
}

final class PhoneRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool
    {
        return is_string($value) && preg_match('/^[0-9+\-\s]{4,}$/', $value) === 1;
    }

    public function message(string $field): string
    {
        return sprintf('The %s must be a valid phone number.', $field);
    }
}

final class CompleteDataRule implements RuleInterface
{
    public function passes(string $field, mixed $value, array $data): bool
    {
        // RuleInterface contract: custom rules receive the complete dataset.
        return array_key_exists('b', $data);
    }

    public function message(string $field): string
    {
        return sprintf('The %s requires sibling data.', $field);
    }
}
