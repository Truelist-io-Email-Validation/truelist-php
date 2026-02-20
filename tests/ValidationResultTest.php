<?php

declare(strict_types=1);

namespace Truelist\Tests;

use PHPUnit\Framework\TestCase;
use Truelist\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function test_from_array_creates_result(): void
    {
        $result = ValidationResult::fromArray([
            'state' => 'valid',
            'sub_state' => 'ok',
            'suggestion' => null,
            'free_email' => true,
            'role' => false,
            'disposable' => false,
        ]);

        $this->assertSame('valid', $result->state);
        $this->assertSame('ok', $result->subState);
        $this->assertNull($result->suggestion);
        $this->assertTrue($result->freeEmail);
        $this->assertFalse($result->role);
        $this->assertFalse($result->disposable);
        $this->assertFalse($result->error);
    }

    public function test_from_array_defaults_missing_fields(): void
    {
        $result = ValidationResult::fromArray([]);

        $this->assertSame('unknown', $result->state);
        $this->assertSame('unknown', $result->subState);
        $this->assertNull($result->suggestion);
        $this->assertFalse($result->freeEmail);
        $this->assertFalse($result->role);
        $this->assertFalse($result->disposable);
    }

    public function test_unknown_error_creates_error_result(): void
    {
        $result = ValidationResult::unknownError();

        $this->assertSame('unknown', $result->state);
        $this->assertSame('unknown', $result->subState);
        $this->assertTrue($result->error);
        $this->assertTrue($result->isError());
        $this->assertTrue($result->isUnknown());
    }

    public function test_is_valid(): void
    {
        $valid = ValidationResult::fromArray(['state' => 'valid', 'sub_state' => 'ok']);
        $this->assertTrue($valid->isValid());
        $this->assertFalse($valid->isInvalid());
        $this->assertFalse($valid->isRisky());
        $this->assertFalse($valid->isUnknown());
    }

    public function test_is_invalid(): void
    {
        $invalid = ValidationResult::fromArray(['state' => 'invalid', 'sub_state' => 'failed_no_mailbox']);
        $this->assertFalse($invalid->isValid());
        $this->assertTrue($invalid->isInvalid());
    }

    public function test_is_risky(): void
    {
        $risky = ValidationResult::fromArray(['state' => 'risky', 'sub_state' => 'accept_all']);
        $this->assertFalse($risky->isValid());
        $this->assertTrue($risky->isRisky());
    }

    public function test_is_unknown(): void
    {
        $unknown = ValidationResult::fromArray(['state' => 'unknown', 'sub_state' => 'unknown']);
        $this->assertFalse($unknown->isValid());
        $this->assertTrue($unknown->isUnknown());
    }

    public function test_is_valid_with_allow_risky(): void
    {
        $valid = ValidationResult::fromArray(['state' => 'valid', 'sub_state' => 'ok']);
        $risky = ValidationResult::fromArray(['state' => 'risky', 'sub_state' => 'accept_all']);
        $invalid = ValidationResult::fromArray(['state' => 'invalid', 'sub_state' => 'failed_no_mailbox']);

        $this->assertTrue($valid->isValid(allowRisky: true));
        $this->assertTrue($risky->isValid(allowRisky: true));
        $this->assertFalse($invalid->isValid(allowRisky: true));
    }

    public function test_is_valid_without_allow_risky_excludes_risky(): void
    {
        $risky = ValidationResult::fromArray(['state' => 'risky', 'sub_state' => 'accept_all']);
        $this->assertFalse($risky->isValid());
        $this->assertFalse($risky->isValid(allowRisky: false));
    }

    public function test_is_free_email(): void
    {
        $free = ValidationResult::fromArray(['state' => 'valid', 'sub_state' => 'ok', 'free_email' => true]);
        $notFree = ValidationResult::fromArray(['state' => 'valid', 'sub_state' => 'ok', 'free_email' => false]);

        $this->assertTrue($free->isFreeEmail());
        $this->assertFalse($notFree->isFreeEmail());
    }

    public function test_is_role(): void
    {
        $role = ValidationResult::fromArray(['state' => 'risky', 'sub_state' => 'role_address', 'role' => true]);
        $notRole = ValidationResult::fromArray(['state' => 'valid', 'sub_state' => 'ok', 'role' => false]);

        $this->assertTrue($role->isRole());
        $this->assertFalse($notRole->isRole());
    }

    public function test_is_disposable(): void
    {
        $disposable = ValidationResult::fromArray([
            'state' => 'invalid',
            'sub_state' => 'disposable_address',
            'disposable' => true,
        ]);
        $notDisposable = ValidationResult::fromArray([
            'state' => 'valid',
            'sub_state' => 'ok',
            'disposable' => false,
        ]);

        $this->assertTrue($disposable->isDisposable());
        $this->assertFalse($notDisposable->isDisposable());
    }

    public function test_suggestion_field(): void
    {
        $result = ValidationResult::fromArray([
            'state' => 'invalid',
            'sub_state' => 'failed_syntax_check',
            'suggestion' => 'user@gmail.com',
        ]);

        $this->assertSame('user@gmail.com', $result->suggestion);
    }

    public function test_is_error_false_for_normal_result(): void
    {
        $result = ValidationResult::fromArray(['state' => 'valid', 'sub_state' => 'ok']);
        $this->assertFalse($result->isError());
    }
}
