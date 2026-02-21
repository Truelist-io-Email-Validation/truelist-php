<?php

declare(strict_types=1);

namespace Truelist;

class AccountInfo
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $uuid,
        public readonly string $timeZone,
        public readonly bool $isAdminRole,
        public readonly string $accountName,
        public readonly string $paymentPlan,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            name: $data['name'] ?? '',
            uuid: $data['uuid'] ?? '',
            timeZone: $data['time_zone'] ?? '',
            isAdminRole: (bool) ($data['is_admin_role'] ?? false),
            accountName: $data['account']['name'] ?? '',
            paymentPlan: $data['account']['payment_plan'] ?? '',
        );
    }
}
