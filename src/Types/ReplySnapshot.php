<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class ReplySnapshot
{
    public function __construct(
        public readonly int     $messageId,
        public readonly ?string $senderId = null,
        public readonly ?string $type     = null,
        public readonly ?string $text     = null,
        public readonly ?string $mediaId  = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            messageId: (int) ($d['message_id'] ?? 0),
            senderId:  $d['sender_id'] ?? null,
            type:      $d['type']      ?? null,
            text:      $d['text']      ?? null,
            mediaId:   $d['media_id']  ?? null,
        );
    }
}
