<?php

declare(strict_types=1);

namespace Truelist;

class AccountInfo
{
    public function __construct(
        public readonly string $email,
        public readonly string $plan,
        public readonly int $credits,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            plan: $data['plan'] ?? '',
            credits: (int) ($data['credits'] ?? 0),
        );
    }
}
