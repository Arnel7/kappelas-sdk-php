<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class ChatInviteLink
{
    public function __construct(
        public readonly string $code,
        public readonly string $url,
        public readonly int    $maxUses   = 0,
        public readonly int    $useCount  = 0,
        public readonly ?int   $expiresAt = null,
        public readonly int    $createdAt = 0,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            code:      (string) ($d['code']       ?? ''),
            url:       (string) ($d['url']        ?? ''),
            maxUses:   (int)    ($d['max_uses']   ?? 0),
            useCount:  (int)    ($d['use_count']  ?? 0),
            expiresAt: isset($d['expires_at']) ? (int) $d['expires_at'] : null,
            createdAt: (int)    ($d['created_at'] ?? 0),
        );
    }
}
