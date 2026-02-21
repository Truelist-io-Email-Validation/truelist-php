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
            'email' => 'team@company.com',
            'name' => 'Team Lead',
            'uuid' => 'a3828d19-1234-5678-9abc-def012345678',
            'time_zone' => 'America/New_York',
            'is_admin_role' => true,
            'account' => [
                'name' => 'Company Inc',
                'payment_plan' => 'pro',
            ],
        ]);

        $this->assertSame('team@company.com', $account->email);
        $this->assertSame('Team Lead', $account->name);
        $this->assertSame('a3828d19-1234-5678-9abc-def012345678', $account->uuid);
        $this->assertSame('America/New_York', $account->timeZone);
        $this->assertTrue($account->isAdminRole);
        $this->assertSame('Company Inc', $account->accountName);
        $this->assertSame('pro', $account->paymentPlan);
    }

    public function test_from_array_defaults_missing_fields(): void
    {
        $account = AccountInfo::fromArray([]);

        $this->assertSame('', $account->email);
        $this->assertSame('', $account->name);
        $this->assertSame('', $account->uuid);
        $this->assertSame('', $account->timeZone);
        $this->assertFalse($account->isAdminRole);
        $this->assertSame('', $account->accountName);
        $this->assertSame('', $account->paymentPlan);
    }

    public function test_properties_are_readonly(): void
    {
        $account = AccountInfo::fromArray([
            'email' => 'team@company.com',
            'name' => 'Team Lead',
            'uuid' => 'a3828d19-1234-5678-9abc-def012345678',
            'time_zone' => 'America/New_York',
            'is_admin_role' => true,
            'account' => [
                'name' => 'Company Inc',
                'payment_plan' => 'pro',
            ],
        ]);

        $reflection = new \ReflectionClass($account);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
