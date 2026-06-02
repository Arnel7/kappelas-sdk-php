<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityGroupRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $communityId,
        public readonly int $conversationId,
        public readonly string $groupName,
        public readonly string $requestedBy,
        public readonly string $status,
        public readonly string $createdAt,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id: (int) ($d['id'] ?? 0),
            communityId: (int) ($d['community_id'] ?? 0),
            conversationId: (int) ($d['conversation_id'] ?? 0),
            groupName: (string) ($d['group_name'] ?? ''),
            requestedBy: (string) ($d['requested_by'] ?? ''),
            status: (string) ($d['status'] ?? ''),
            createdAt: (string) ($d['created_at'] ?? ''),
        );
    }
}
