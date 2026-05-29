<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class GetChatInviteLinksResult
{
    /** @param ChatInviteLink[] $inviteLinks */
    public function __construct(
        public readonly array $inviteLinks,
    ) {}

    public static function fromArray(array $d): self
    {
        $links = array_map(
            static fn(array $l): ChatInviteLink => ChatInviteLink::fromArray($l),
            $d['invite_links'] ?? [],
        );
        return new self(inviteLinks: $links);
    }
}
