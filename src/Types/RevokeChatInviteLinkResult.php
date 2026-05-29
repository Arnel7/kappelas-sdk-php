<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class RevokeChatInviteLinkResult
{
    public function __construct(
        public readonly bool   $revoked,
        public readonly string $code,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            revoked: (bool)   ($d['revoked'] ?? false),
            code:    (string) ($d['code']    ?? ''),
        );
    }
}
