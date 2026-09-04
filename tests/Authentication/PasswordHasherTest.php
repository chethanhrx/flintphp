<?php

declare(strict_types=1);

namespace FlintPHP\Framework\Tests\Authentication;

use FlintPHP\Framework\Authentication\PasswordHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_handles_empty_hash_verification_safely(): void
    {
        $this->assertFalse($this->hasher->verify('secret123', ''));
    }

    #[Test]
    public function it_identifies_when_rehashing_is_needed(): void
    {
        // Generate a valid bcrypt hash with a low cost to trigger needsRehash
        // A cost of 4 is the minimum for bcrypt. The default is higher (usually 10 or 12).
        $options = ['cost' => 4];
        $lowCostHash = password_hash('secret123', PASSWORD_BCRYPT, $options);

        // This will be true because PASSWORD_DEFAULT uses a higher cost
        $this->assertTrue($this->hasher->needsRehash($lowCostHash));

        $normalHash = $this->hasher->hash('secret123');
        $this->assertFalse($this->hasher->needsRehash($normalHash));
    }
}
