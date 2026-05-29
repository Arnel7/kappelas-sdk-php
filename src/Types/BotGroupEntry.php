<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class BotGroupEntry
{
    public function __construct(
        public readonly int     $chatId,
        /** @var 'private'|'group'|'channel' */
        public readonly string  $type,
        public readonly ?string $title,
        public readonly int     $participantCount,
        /** @var 'member'|'admin' */
        public readonly string  $botRole,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            chatId:           (int)    ($d['chat_id']           ?? 0),
            type:             (string) ($d['type']              ?? 'group'),
            title:            $d['title'] ?? null,
            participantCount: (int)    ($d['participant_count'] ?? 0),
            botRole:          (string) ($d['bot_role']          ?? 'member'),
        );
    }
}
