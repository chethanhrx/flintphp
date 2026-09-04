<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Validation;

use FlintPHP\Framework\Validation\Rules\Min;
use FlintPHP\Framework\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function it_passes_valid_data(): void
    {
        $data = [
            'email' => 'test@example.com',
            'age' => 25,
            'role' => 'admin',
        ];

        $rules = [
            'email' => ['required', 'string', 'email'],
            'age' => ['required', 'integer', 'min:18', 'max:100'],
            'role' => ['required', 'in:admin,user'],
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
        $this->assertSame($data, $result->validated());
    }

    #[Test]
    public function it_fails_invalid_data_and_returns_messages(): void
    {
        $data = [
            'email' => 'invalid-email',
            'age' => 17,
            'role' => 'guest',
        ];

        $rules = [
            'email' => ['required', 'email'],
            'age' => ['required', 'integer', 'min:18'],
            'role' => ['required', 'in:admin,user'],
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertFalse($result->isValid());
        $errors = $result->errors();

        $this->assertCount(3, $errors);
        $this->assertSame('The email must be a valid email address.', $errors['email'][0]);
        $this->assertSame('The age must be at least 18.', $errors['age'][0]);
        $this->assertSame('The selected role is invalid.', $errors['role'][0]);
    }

    #[Test]
    public function validated_array_isolates_valid_data_and_preserves_values(): void
    {
        $data = [
            'age' => '42',
            'email' => 'user@example.com',
            'unvalidated' => 'secret',
        ];

        $rules = [
            'age' => ['integer', 'min:18'],
            'email' => ['email'],
        ];

        $result = $this->validator->validate($data, $rules);

        $this->assertTrue($result->isValid());
        $validated = $result->validated();
        
        $this->assertCount(2, $validated);
        $this->assertSame('42', $validated['age']);
        $this->assertSame('user@example.com', $validated['email']);
        $this->assertArrayNotHasKey('unvalidated', $validated);
    }

    #[Test]
    public function missing_null_and_empty_strings_fail_required(): void
    {
        $rules = ['field' => ['required']];

        $this->assertFalse($this->validator->validate([], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => null], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => ''], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => '   '], $rules)->isValid());
    }

    #[Test]
    public function zero_and_false_pass_required(): void
    {
        $rules = ['field' => ['required']];

        $this->assertTrue($this->validator->validate(['field' => 0], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['field' => '0'], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['field' => false], $rules)->isValid());
    }

    #[Test]
    public function it_implicitly_skips_rules_if_optional_field_is_empty(): void
    {
        $rules = ['age' => ['integer', 'min:18']];

        // Should skip validation because it's missing/null/empty and NOT required
        $this->assertTrue($this->validator->validate([], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['age' => null], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['age' => ''], $rules)->isValid());
    }

    #[Test]
    public function integer_rule_strictly_evaluates_without_blind_casting(): void
    {
        $rules = ['count' => ['required', 'integer']];

        $this->assertTrue($this->validator->validate(['count' => 42], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['count' => '42'], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['count' => '-10'], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['count' => '0'], $rules)->isValid());

        $this->assertFalse($this->validator->validate(['count' => '42abc'], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['count' => 'abc'], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['count' => 42.5], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['count' => '42.5'], $rules)->isValid());
    }

    #[Test]
    public function object_rules_are_supported(): void
    {
        $rules = [
            'age' => [new Min(18)]
        ];

        $this->assertTrue($this->validator->validate(['age' => 20], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['age' => 17], $rules)->isValid());
    }

    #[Test]
    public function unknown_string_rule_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation rule "unknown" is not supported');

        $this->validator->validate(['field' => 'val'], ['field' => ['unknown']]);
    }

    #[Test]
    public function min_rule_without_parameter_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation rule "min" requires a parameter.');

        $this->validator->validate(['field' => 'val'], ['field' => ['min:']]);
    }

    #[Test]
    public function min_rule_with_non_numeric_parameter_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation rule "min" requires a numeric parameter.');

        $this->validator->validate(['field' => 'val'], ['field' => ['min:abc']]);
    }
}
