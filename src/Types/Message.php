<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class Message
{
    public function __construct(
        public readonly int     $messageId,
        public readonly int     $chatId,
        public readonly ?string $text        = null,
        public readonly ?string $type        = null,
        public readonly ?string $senderId    = null,
        public readonly ?string $senderNom   = null,
        public readonly ?string $senderUsername = null,
        public readonly ?int    $sentAt      = null,
        public readonly ?int    $replyToId   = null,
        public readonly ?string $mediaId     = null,
        public readonly ?string $mediaUrl    = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            messageId:       (int) ($d['message_id']       ?? 0),
            chatId:          (int) ($d['chat_id']          ?? 0),
            text:            $d['text']                    ?? null,
            type:            $d['type']                    ?? null,
            senderId:        $d['sender_id']               ?? null,
            senderNom:       $d['sender_nom']              ?? null,
            senderUsername:  $d['sender_username']         ?? null,
            sentAt:          isset($d['sent_at'])    ? (int) $d['sent_at']    : null,
            replyToId:       isset($d['reply_to_id']) ? (int) $d['reply_to_id'] : null,
            mediaId:         $d['media_id']                ?? null,
            mediaUrl:        $d['media_url']               ?? null,
        );
    }
}
