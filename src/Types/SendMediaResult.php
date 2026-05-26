<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class SendMediaResult
{
    public function __construct(
        public readonly int     $messageId,
        public readonly ?int    $createdAt = null,
        public readonly ?string $mediaId   = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            messageId: (int) ($d['message_id'] ?? 0),
            createdAt: isset($d['created_at']) ? (int) $d['created_at'] : null,
            mediaId:   $d['media_id'] ?? null,
        );
    }
}
