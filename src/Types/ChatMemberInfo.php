<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class ChatMemberInfo
{
    public function __construct(
        public readonly string $userId,
        /** @var 'member'|'admin' */
        public readonly string $role,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            userId: (string) ($d['user_id'] ?? ''),
            role:   (string) ($d['role']    ?? 'member'),
        );
    }
}
