<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Chat
{
    public function __construct(
        public readonly int     $chatId,
        public readonly string  $type,
        public readonly ?string $name        = null,
        public readonly ?string $username    = null,
        public readonly ?string $avatarUrl   = null,
        public readonly ?int    $lastMessageAt = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            chatId:        (int) ($d['chat_id']         ?? 0),
            type:          $d['type']                   ?? 'unknown',
            name:          $d['name']                   ?? null,
            username:      $d['username']               ?? null,
            avatarUrl:     $d['avatar_url']             ?? null,
            lastMessageAt: isset($d['last_message_at']) ? (int) $d['last_message_at'] : null,
        );
    }
}
