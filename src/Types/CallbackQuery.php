<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CallbackQuery
{
    public function __construct(
        public readonly int     $chatId,
        public readonly string  $senderId,
        public readonly string  $callbackData,
        public readonly ?string $senderName     = null,
        public readonly ?string $senderUsername = null,
        public readonly ?int    $sentAt         = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            chatId:         (int) ($d['chat_id']          ?? 0),
            senderId:       $d['sender_id']               ?? '',
            callbackData:   $d['callback_data']           ?? '',
            senderName:     $d['sender_name'] ?? $d['sender_nom'] ?? null,
            senderUsername: $d['sender_username']         ?? null,
            sentAt:         isset($d['sent_at']) ? (int) $d['sent_at'] : null,
        );
    }
}
