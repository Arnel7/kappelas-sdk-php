<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityInvite
{
    public function __construct(
        public readonly string $code,
        public readonly int $communityId,
        public readonly string $createdBy,
        public readonly int $maxUses,
        public readonly int $useCount,
        public readonly string $createdAt,
        public readonly ?string $expiresAt = null,
        public readonly ?string $revokedAt = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            code: (string) ($d['code'] ?? ''),
            communityId: (int) ($d['community_id'] ?? 0),
            createdBy: (string) ($d['created_by'] ?? ''),
            maxUses: (int) ($d['max_uses'] ?? 0),
            useCount: (int) ($d['use_count'] ?? 0),
            createdAt: (string) ($d['created_at'] ?? ''),
            expiresAt: $d['expires_at'] ?? null,
            revokedAt: $d['revoked_at'] ?? null,
        );
    }
}
