<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class BotProfile
{
    public function __construct(
        public readonly string  $userId,
        public readonly string  $username,
        public readonly bool    $isBot,
        public readonly ?string $about       = null,
        public readonly ?string $description = null,
        public readonly ?string $avatarUrl   = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            userId:      $d['user_id']    ?? $d['id']  ?? '',
            username:    $d['username']   ?? '',
            isBot:       (bool) ($d['is_bot'] ?? true),
            about:       $d['about']      ?? null,
            description: $d['description'] ?? null,
            avatarUrl:   $d['avatar_url'] ?? null,
        );
    }
}
