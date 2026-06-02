<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Community
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $createdBy,
        public readonly bool $requiresApproval,
        public readonly string $createdAt,
        public readonly ?string $description = null,
        public readonly ?string $avatarUrl = null,
        public readonly ?int $announcementChannelId = null,
        /** Role du bot/user DANS LA COMMUNAUTE ('member'|'admin'), via list() seulement. */
        public readonly ?string $role = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id: (int) ($d['id'] ?? 0),
            name: (string) ($d['name'] ?? ''),
            createdBy: (string) ($d['created_by'] ?? ''),
            requiresApproval: (bool) ($d['requires_approval'] ?? false),
            createdAt: (string) ($d['created_at'] ?? ''),
            description: $d['description'] ?? null,
            avatarUrl: $d['avatar_url'] ?? null,
            announcementChannelId: isset($d['announcement_channel_id']) ? (int) $d['announcement_channel_id'] : null,
            role: $d['role'] ?? null,
        );
    }
}
