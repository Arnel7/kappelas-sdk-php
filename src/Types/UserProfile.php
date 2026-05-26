<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class UserProfile
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $username,
        public readonly ?string $nom        = null,
        public readonly bool    $isBot      = false,
        public readonly bool    $isPremium  = false,
        public readonly ?string $avatarUrl  = null,
        public readonly ?string $about      = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id:        $d['id']        ?? $d['user_id'] ?? '',
            username:  $d['username']  ?? '',
            nom:       $d['nom']       ?? null,
            isBot:     (bool) ($d['is_bot']     ?? false),
            isPremium: (bool) ($d['is_premium']  ?? false),
            avatarUrl: $d['avatar_url'] ?? null,
            about:     $d['about']     ?? null,
        );
    }
}
