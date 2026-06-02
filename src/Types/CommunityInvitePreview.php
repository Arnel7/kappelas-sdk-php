<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityInvitePreview
{
    public function __construct(
        public readonly string $code,
        public readonly int $communityId,
        public readonly string $communityName,
        public readonly int $memberCount,
        public readonly ?string $expiresAt = null,
        public readonly ?string $avatarUrl = null,
        public readonly ?string $description = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            code: (string) ($d['code'] ?? ''),
            communityId: (int) ($d['community_id'] ?? 0),
            communityName: (string) ($d['community_name'] ?? ''),
            memberCount: (int) ($d['member_count'] ?? 0),
            expiresAt: $d['expires_at'] ?? null,
            avatarUrl: $d['avatar_url'] ?? null,
            description: $d['description'] ?? null,
        );
    }
}
