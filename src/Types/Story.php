<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Story
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $mediaId,
        public readonly string $mediaType, // 'image' | 'video' | 'text' | 'poll'
        public readonly string $caption,
        public readonly string $expiresAt, // ISO 8601
        public readonly int $viewCount,
        public readonly string $createdAt, // ISO 8601
        public readonly string $audience,  // 'all' | 'selected' | 'excluded'
        /** @var string[]|null */
        public readonly ?array $audienceUserIds = null,
        public readonly ?string $authorName = null,
        public readonly ?string $authorAvatar = null,
        public readonly bool $viewedByMe = false,
        public readonly ?string $mediaUrl = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            userId: (string) ($d['user_id'] ?? ''),
            mediaId: (string) ($d['media_id'] ?? ''),
            mediaType: (string) ($d['media_type'] ?? ''),
            caption: (string) ($d['caption'] ?? ''),
            expiresAt: (string) ($d['expires_at'] ?? ''),
            viewCount: (int) ($d['view_count'] ?? 0),
            createdAt: (string) ($d['created_at'] ?? ''),
            audience: (string) ($d['audience'] ?? 'all'),
            audienceUserIds: $d['audience_user_ids'] ?? null,
            authorName: $d['author_name'] ?? null,
            authorAvatar: $d['author_avatar'] ?? null,
            viewedByMe: (bool) ($d['viewed_by_me'] ?? false),
            mediaUrl: $d['media_url'] ?? null,
        );
    }
}
