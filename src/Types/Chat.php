<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Chat
{
    /**
     * @param Participant[] $participants
     * @param array<mixed>  $labels
     */
    public function __construct(
        public readonly int     $chatId,
        public readonly int     $id,
        /** @var 'private'|'group'|'channel' */
        public readonly string  $type,
        public readonly ?string $title                = null,
        public readonly array   $participants         = [],
        public readonly mixed   $lastMessageAt        = null,
        public readonly string  $createdAt            = '',
        public readonly string  $createdBy            = '',
        public readonly bool    $isPinned             = false,
        public readonly bool    $isPremium            = false,
        public readonly bool    $isPublic             = false,
        public readonly bool    $onlyAdminsCanWrite   = false,
        public readonly array   $labels               = [],
        public readonly ?string $description          = null,
        public readonly ?string $avatarUrl            = null,
    ) {}

    public static function fromArray(array $d): self
    {
        $participants = array_map(
            static fn(array $p): Participant => Participant::fromArray($p),
            $d['participants'] ?? [],
        );
        return new self(
            chatId:              (int)    ($d['chat_id']               ?? 0),
            id:                  (int)    ($d['id']                    ?? 0),
            type:                (string) ($d['type']                  ?? 'private'),
            title:               $d['title']      ?? $d['name']        ?? null,
            participants:        $participants,
            lastMessageAt:       $d['last_message_at']                 ?? null,
            createdAt:           (string) ($d['created_at']            ?? ''),
            createdBy:           (string) ($d['created_by']            ?? ''),
            isPinned:            (bool)   ($d['is_pinned']             ?? false),
            isPremium:           (bool)   ($d['is_premium']            ?? false),
            isPublic:            (bool)   ($d['is_public']             ?? false),
            onlyAdminsCanWrite:  (bool)   ($d['only_admins_can_write'] ?? false),
            labels:              (array)  ($d['labels']                ?? []),
            description:         $d['description']                     ?? null,
            avatarUrl:           $d['avatar_url']                      ?? null,
        );
    }
}
