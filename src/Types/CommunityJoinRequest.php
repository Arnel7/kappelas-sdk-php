<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityJoinRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $communityId,
        public readonly string $userId,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly ?string $requesterName = null,
        public readonly ?string $requesterAvatarUrl = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id: (int) ($d['id'] ?? 0),
            communityId: (int) ($d['community_id'] ?? 0),
            userId: (string) ($d['user_id'] ?? ''),
            status: (string) ($d['status'] ?? ''),
            createdAt: (string) ($d['created_at'] ?? ''),
            requesterName: $d['requester_name'] ?? null,
            requesterAvatarUrl: $d['requester_avatar_url'] ?? null,
        );
    }
}
