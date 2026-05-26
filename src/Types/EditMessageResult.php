<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class EditMessageResult
{
    public function __construct(
        public readonly bool $edited,
        public readonly int  $messageId,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            edited:    (bool) ($d['edited']     ?? false),
            messageId: (int)  ($d['message_id'] ?? 0),
        );
    }
}
