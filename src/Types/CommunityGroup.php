<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityGroup
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly bool $joined,
        public readonly bool $pending,
        public readonly int $participantsCount,
        public readonly ?string $title = null,
        public readonly ?string $avatarUrl = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id: (int) ($d['id'] ?? 0),
            type: (string) ($d['type'] ?? ''),
            joined: (bool) ($d['joined'] ?? false),
            pending: (bool) ($d['pending'] ?? false),
            participantsCount: (int) ($d['participants_count'] ?? 0),
            title: $d['title'] ?? null,
            avatarUrl: $d['avatar_url'] ?? null,
        );
    }
}
