<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityMember
{
    public function __construct(
        public readonly int $communityId,
        public readonly string $userId,
        public readonly string $role,
        public readonly string $joinedAt,
        public readonly ?string $name = null,
        public readonly ?string $avatarUrl = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            communityId: (int) ($d['community_id'] ?? 0),
            userId: (string) ($d['user_id'] ?? ''),
            role: (string) ($d['role'] ?? 'member'),
            joinedAt: (string) ($d['joined_at'] ?? ''),
            name: $d['name'] ?? null,
            avatarUrl: $d['avatar_url'] ?? null,
        );
    }
}
