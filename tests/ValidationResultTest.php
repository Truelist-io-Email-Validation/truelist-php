<?php

declare(strict_types=1);

namespace Truelist\Tests;

use PHPUnit\Framework\TestCase;
use Truelist\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function test_from_api_response_creates_result(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [[
                'address' => 'user@example.com',
                'domain' => 'example.com',
                'canonical' => 'user',
                'mx_record' => null,
                'first_name' => null,
                'last_name' => null,
                'email_state' => 'ok',
                'email_sub_state' => 'email_ok',
                'verified_at' => '2026-02-21T10:00:00.000Z',
                'did_you_mean' => null,
            ]],
        ]);

        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('ok', $result->state);
        $this->assertSame('email_ok', $result->subState);
        $this->assertNull($result->suggestion);
        $this->assertSame('example.com', $result->domain);
        $this->assertSame('user', $result->canonical);
        $this->assertNull($result->mxRecord);
        $this->assertNull($result->firstName);
        $this->assertNull($result->lastName);
        $this->assertSame('2026-02-21T10:00:00.000Z', $result->verifiedAt);
        $this->assertFalse($result->error);
    }

    public function test_from_api_response_defaults_missing_fields(): void
    {
        $result = ValidationResult::fromApiResponse(['emails' => [[] ]]);

        $this->assertSame('', $result->email);
        $this->assertSame('unknown', $result->state);
        $this->assertSame('unknown_error', $result->subState);
        $this->assertNull($result->suggestion);
    }

    public function test_from_api_response_handles_empty_emails_array(): void
    {
        $result = ValidationResult::fromApiResponse(['emails' => []]);

        $this->assertSame('', $result->email);
        $this->assertSame('unknown', $result->state);
        $this->assertSame('unknown_error', $result->subState);
    }

    public function test_unknown_error_creates_error_result(): void
    {
        $result = ValidationResult::unknownError();

        $this->assertSame('', $result->email);
        $this->assertSame('unknown', $result->state);
        $this->assertSame('unknown_error', $result->subState);
        $this->assertTrue($result->error);
        $this->assertTrue($result->isError());
        $this->assertTrue($result->isUnknown());
    }

    public function test_is_valid(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'ok', 'email_sub_state' => 'email_ok']],
        ]);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isInvalid());
        $this->assertFalse($result->isAcceptAll());
        $this->assertFalse($result->isUnknown());
    }

    public function test_is_invalid(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'email_invalid', 'email_sub_state' => 'failed_smtp_check']],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isInvalid());
    }

    public function test_is_accept_all(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'accept_all', 'email_sub_state' => 'email_ok']],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isAcceptAll());
    }

    public function test_is_unknown(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'unknown', 'email_sub_state' => 'unknown_error']],
        ]);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->isUnknown());
    }

    public function test_is_role(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'ok', 'email_sub_state' => 'is_role']],
        ]);

        $this->assertTrue($result->isRole());
    }

    public function test_is_not_role(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'ok', 'email_sub_state' => 'email_ok']],
        ]);

        $this->assertFalse($result->isRole());
    }

    public function test_is_disposable(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'email_invalid', 'email_sub_state' => 'is_disposable']],
        ]);

        $this->assertTrue($result->isDisposable());
    }

    public function test_is_not_disposable(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'ok', 'email_sub_state' => 'email_ok']],
        ]);

        $this->assertFalse($result->isDisposable());
    }

    public function test_suggestion_field(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [[
                'email_state' => 'email_invalid',
                'email_sub_state' => 'failed_smtp_check',
                'did_you_mean' => 'user@gmail.com',
            ]],
        ]);

        $this->assertSame('user@gmail.com', $result->suggestion);
    }

    public function test_is_error_false_for_normal_result(): void
    {
        $result = ValidationResult::fromApiResponse([
            'emails' => [['email_state' => 'ok', 'email_sub_state' => 'email_ok']],
        ]);

        $this->assertFalse($result->isError());
    }
}
