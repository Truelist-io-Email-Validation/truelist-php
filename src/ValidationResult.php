<?php

declare(strict_types=1);

namespace Truelist;

class ValidationResult
{
    public function __construct(
        public readonly string $email,
        public readonly string $state,
        public readonly string $subState,
        public readonly ?string $suggestion,
        public readonly ?string $domain,
        public readonly ?string $canonical,
        public readonly ?string $mxRecord,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $verifiedAt,
        public readonly bool $error = false,
    ) {
    }

    public static function fromApiResponse(array $data): self
    {
        $email = $data['emails'][0] ?? [];

        return new self(
            email: $email['address'] ?? '',
            state: $email['email_state'] ?? 'unknown',
            subState: $email['email_sub_state'] ?? 'unknown_error',
            suggestion: $email['did_you_mean'] ?? null,
            domain: $email['domain'] ?? null,
            canonical: $email['canonical'] ?? null,
            mxRecord: $email['mx_record'] ?? null,
            firstName: $email['first_name'] ?? null,
            lastName: $email['last_name'] ?? null,
            verifiedAt: $email['verified_at'] ?? null,
        );
    }

    public static function unknownError(): self
    {
        return new self(
            email: '',
            state: 'unknown',
            subState: 'unknown_error',
            suggestion: null,
            domain: null,
            canonical: null,
            mxRecord: null,
            firstName: null,
            lastName: null,
            verifiedAt: null,
            error: true,
        );
    }

    public function isValid(): bool
    {
        return $this->state === 'ok';
    }

    public function isInvalid(): bool
    {
        return $this->state === 'email_invalid';
    }

    public function isAcceptAll(): bool
    {
        return $this->state === 'accept_all';
    }

    public function isUnknown(): bool
    {
        return $this->state === 'unknown';
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function isRole(): bool
    {
        return $this->subState === 'is_role';
    }

    public function isDisposable(): bool
    {
        return $this->subState === 'is_disposable';
    }
}
