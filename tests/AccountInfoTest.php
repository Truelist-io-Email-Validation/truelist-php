<?php

declare(strict_types=1);

namespace Truelist\Tests;

use PHPUnit\Framework\TestCase;
use Truelist\AccountInfo;

class AccountInfoTest extends TestCase
{
    public function test_from_array_creates_account_info(): void
    {
        $account = AccountInfo::fromArray([
            'email' => 'user@example.com',
            'plan' => 'pro',
            'credits' => 9542,
        ]);

        $this->assertSame('user@example.com', $account->email);
        $this->assertSame('pro', $account->plan);
        $this->assertSame(9542, $account->credits);
    }

    public function test_from_array_defaults_missing_fields(): void
    {
        $account = AccountInfo::fromArray([]);

        $this->assertSame('', $account->email);
        $this->assertSame('', $account->plan);
        $this->assertSame(0, $account->credits);
    }

    public function test_credits_cast_to_int(): void
    {
        $account = AccountInfo::fromArray([
            'email' => 'user@example.com',
            'plan' => 'starter',
            'credits' => '500',
        ]);

        $this->assertSame(500, $account->credits);
    }

    public function test_properties_are_readonly(): void
    {
        $account = AccountInfo::fromArray([
            'email' => 'user@example.com',
            'plan' => 'pro',
            'credits' => 100,
        ]);

        $reflection = new \ReflectionClass($account);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
