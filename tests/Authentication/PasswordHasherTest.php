<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\PasswordHasher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PasswordHasher::class)]
final class PasswordHasherTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    #[Test]
    public function it_can_hash_and_verify_passwords(): void
    {
        $password = 'secret123';
        $hash = $this->hasher->hash($password);

        $this->assertNotSame($password, $hash);
        $this->assertTrue($this->hasher->verify($password, $hash));
        $this->assertFalse($this->hasher->verify('wrong', $hash));
    }

    #[Test]
    public function it_rejects_empty_password_on_hash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not be empty.');

        $this->hasher->hash('');
    }

    #[Test]
    public function it_handles_empty_password_or_hash_on_verify(): void
    {
        $hash = $this->hasher->hash('secret123');

        $this->assertFalse($this->hasher->verify('', $hash));
        $this->assertFalse($this->hasher->verify('secret123', ''));
        $this->assertFalse($this->hasher->verify('', ''));
    }

    #[Test]
    public function it_handles_malformed_hash_safely(): void
    {
        $this->assertFalse($this->hasher->verify('secret123', 'not-a-valid-argon-or-bcrypt-hash'));
        $this->assertFalse($this->hasher->verify('secret123', '$2y$04$tooshort'));
    }

    #[Test]
    public function it_throws_runtime_exception_on_hashing_failure(): void
    {
        // Cost of 3 is invalid for BCRYPT (minimum allowed by PHP is 4), which causes password_hash to fail
        $brokenHasher = new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 3]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to hash password');

        $brokenHasher->hash('password');
    }

    #[Test]
    public function it_identifies_when_rehashing_is_needed(): void
    {
        // Generate a valid bcrypt hash with cost 4
        $options = ['cost' => 4];
        $lowCostHash = password_hash('secret123', PASSWORD_BCRYPT, $options);

        // PASSWORD_DEFAULT has higher cost
        $this->assertTrue($this->hasher->needsRehash($lowCostHash));

        $normalHash = $this->hasher->hash('secret123');
        $this->assertFalse($this->hasher->needsRehash($normalHash));

        // Empty hash always needs rehash
        $this->assertTrue($this->hasher->needsRehash(''));
    }

    #[Test]
    public function it_respects_custom_algorithm_and_options_in_needs_rehash(): void
    {
        $customHasher = new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 12]);
        $hashCost10 = password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 10]);

        $this->assertTrue($customHasher->needsRehash($hashCost10));

        $hashCost12 = $customHasher->hash('secret123');
        $this->assertFalse($customHasher->needsRehash($hashCost12));
    }
}
