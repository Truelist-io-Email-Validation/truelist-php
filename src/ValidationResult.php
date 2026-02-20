<?php

declare(strict_types=1);

namespace Truelist;

class ValidationResult
{
    public function __construct(
        public readonly string $state,
        public readonly string $subState,
        public readonly ?string $suggestion,
        public readonly bool $freeEmail,
        public readonly bool $role,
        public readonly bool $disposable,
        public readonly bool $error = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            state: $data['state'] ?? 'unknown',
            subState: $data['sub_state'] ?? 'unknown',
            suggestion: $data['suggestion'] ?? null,
            freeEmail: (bool) ($data['free_email'] ?? false),
            role: (bool) ($data['role'] ?? false),
            disposable: (bool) ($data['disposable'] ?? false),
        );
    }

    public static function unknownError(): self
    {
        return new self(
            state: 'unknown',
            subState: 'unknown',
            suggestion: null,
            freeEmail: false,
            role: false,
            disposable: false,
            error: true,
        );
    }

    public function isValid(bool $allowRisky = false): bool
    {
        if ($allowRisky) {
            return $this->state === 'valid' || $this->state === 'risky';
        }

        return $this->state === 'valid';
    }

    public function isInvalid(): bool
    {
        return $this->state === 'invalid';
    }

    public function isRisky(): bool
    {
        return $this->state === 'risky';
    }

    public function isUnknown(): bool
    {
        return $this->state === 'unknown';
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function isFreeEmail(): bool
    {
        return $this->freeEmail;
    }

    public function isRole(): bool
    {
        return $this->role;
    }

    public function isDisposable(): bool
    {
        return $this->disposable;
    }
}
