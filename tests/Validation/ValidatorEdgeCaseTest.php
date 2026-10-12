<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Validation;

use FlintPHP\Framework\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Edge-case tests for individual validation rules.
 *
 * Covers gaps from Phase A audit:
 * - max rule standalone edge cases
 * - string rule rejects non-strings
 * - email rule edge cases
 * - min/max boundary values
 * - in rule edge cases
 */
#[CoversClass(Validator::class)]
final class ValidatorEdgeCaseTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    // ---------------------------------------------------------------
    // string rule
    // ---------------------------------------------------------------

    #[Test]
    public function string_rule_rejects_integer(): void
    {
        $rules = ['field' => ['required', 'string']];

        $this->assertFalse($this->validator->validate(['field' => 42], $rules)->isValid());
    }

    #[Test]
    public function string_rule_rejects_array(): void
    {
        $rules = ['field' => ['required', 'string']];

        $this->assertFalse($this->validator->validate(['field' => ['a', 'b']], $rules)->isValid());
    }

    #[Test]
    public function string_rule_rejects_boolean(): void
    {
        $rules = ['field' => ['required', 'string']];

        $this->assertFalse($this->validator->validate(['field' => true], $rules)->isValid());
    }

    #[Test]
    public function string_rule_accepts_string(): void
    {
        $rules = ['field' => ['required', 'string']];

        $this->assertTrue($this->validator->validate(['field' => 'hello'], $rules)->isValid());
    }

    #[Test]
    public function string_rule_accepts_numeric_string(): void
    {
        $rules = ['field' => ['required', 'string']];

        $this->assertTrue($this->validator->validate(['field' => '42'], $rules)->isValid());
    }

    // ---------------------------------------------------------------
    // max rule
    // ---------------------------------------------------------------

    #[Test]
    public function max_rule_with_numeric_value_at_boundary(): void
    {
        $rules = ['field' => ['required', 'max:100']];

        // At boundary
        $this->assertTrue($this->validator->validate(['field' => 100], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['field' => '100'], $rules)->isValid());

        // Over boundary
        $this->assertFalse($this->validator->validate(['field' => 101], $rules)->isValid());
    }

    #[Test]
    public function max_rule_with_string_measures_length(): void
    {
        $rules = ['field' => ['required', 'string', 'max:5']];

        $this->assertTrue($this->validator->validate(['field' => 'abcde'], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => 'abcdef'], $rules)->isValid());
    }

    #[Test]
    public function max_rule_without_parameter_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a parameter');

        $this->validator->validate(['field' => 'val'], ['field' => ['max:']]);
    }

    #[Test]
    public function max_rule_with_non_numeric_parameter_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a numeric parameter');

        $this->validator->validate(['field' => 'val'], ['field' => ['max:abc']]);
    }

    // ---------------------------------------------------------------
    // min rule boundary
    // ---------------------------------------------------------------

    #[Test]
    public function min_rule_at_exact_boundary_passes(): void
    {
        $rules = ['field' => ['required', 'min:18']];

        $this->assertTrue($this->validator->validate(['field' => 18], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => 17], $rules)->isValid());
    }

    #[Test]
    public function min_rule_with_zero_boundary(): void
    {
        $rules = ['field' => ['required', 'min:0']];

        $this->assertTrue($this->validator->validate(['field' => 0], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => -1], $rules)->isValid());
    }

    #[Test]
    public function min_rule_with_negative_boundary(): void
    {
        $rules = ['field' => ['required', 'min:-10']];

        $this->assertTrue($this->validator->validate(['field' => -10], $rules)->isValid());
        $this->assertTrue($this->validator->validate(['field' => 0], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['field' => -11], $rules)->isValid());
    }

    // ---------------------------------------------------------------
    // email rule edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function email_rejects_at_sign_only(): void
    {
        $rules = ['email' => ['required', 'email']];

        $this->assertFalse($this->validator->validate(['email' => '@'], $rules)->isValid());
    }

    #[Test]
    public function email_rejects_missing_domain(): void
    {
        $rules = ['email' => ['required', 'email']];

        $this->assertFalse($this->validator->validate(['email' => 'user@'], $rules)->isValid());
    }

    #[Test]
    public function email_rejects_missing_local_part(): void
    {
        $rules = ['email' => ['required', 'email']];

        $this->assertFalse($this->validator->validate(['email' => '@domain.com'], $rules)->isValid());
    }

    #[Test]
    public function email_accepts_valid_complex_email(): void
    {
        $rules = ['email' => ['required', 'email']];

        $this->assertTrue($this->validator->validate(['email' => 'user+tag@example.com'], $rules)->isValid());
    }

    #[Test]
    public function email_rejects_non_string(): void
    {
        $rules = ['email' => ['required', 'email']];

        $this->assertFalse($this->validator->validate(['email' => 42], $rules)->isValid());
    }

    // ---------------------------------------------------------------
    // in rule edge cases
    // ---------------------------------------------------------------

    #[Test]
    public function in_rule_is_case_sensitive(): void
    {
        $rules = ['role' => ['required', 'in:admin,user']];

        $this->assertTrue($this->validator->validate(['role' => 'admin'], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['role' => 'Admin'], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['role' => 'ADMIN'], $rules)->isValid());
    }

    #[Test]
    public function in_rule_handles_single_option(): void
    {
        $rules = ['status' => ['required', 'in:active']];

        $this->assertTrue($this->validator->validate(['status' => 'active'], $rules)->isValid());
        $this->assertFalse($this->validator->validate(['status' => 'inactive'], $rules)->isValid());
    }

    // ---------------------------------------------------------------
    // Multiple validation errors on same field
    // ---------------------------------------------------------------

    #[Test]
    public function multiple_rules_can_produce_multiple_errors(): void
    {
        $rules = [
            'age' => ['required', 'integer', 'min:18'],
        ];

        // 'abc' fails both integer and min checks
        $result = $this->validator->validate(['age' => 'abc'], $rules);
        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->errors()['age']);
    }

    // ---------------------------------------------------------------
    // validated() returns empty on total failure
    // ---------------------------------------------------------------

    #[Test]
    public function validated_returns_empty_when_all_fields_fail(): void
    {
        $rules = [
            'email' => ['required', 'email'],
            'age' => ['required', 'integer'],
        ];

        $result = $this->validator->validate([], $rules);
        $this->assertFalse($result->isValid());
        $this->assertSame([], $result->validated());
    }
}
