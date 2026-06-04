<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class StoryView
{
    public function __construct(
        public readonly string $storyId,
        public readonly string $viewerId,
        public readonly string $viewedAt, // ISO 8601
        public readonly ?string $viewerName = null,
        public readonly ?string $viewerAvatar = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            storyId: (string) ($d['story_id'] ?? ''),
            viewerId: (string) ($d['viewer_id'] ?? ''),
            viewedAt: (string) ($d['viewed_at'] ?? ''),
            viewerName: $d['viewer_name'] ?? null,
            viewerAvatar: $d['viewer_avatar'] ?? null,
        );
    }
}
