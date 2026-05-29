<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Participant
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $nom,
        public readonly bool    $isBot      = false,
        public readonly bool    $isPremium  = false,
        public readonly ?string $avatarUrl  = null,
        /** @var 'member'|'admin'|null */
        public readonly ?string $role       = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id:        (string) ($d['id'] ?? ''),
            nom:       (string) ($d['nom'] ?? ''),
            isBot:     (bool)   ($d['is_bot']     ?? false),
            isPremium: (bool)   ($d['is_premium']  ?? false),
            avatarUrl: $d['avatar_url'] ?? null,
            role:      $d['role']       ?? null,
        );
    }
}
