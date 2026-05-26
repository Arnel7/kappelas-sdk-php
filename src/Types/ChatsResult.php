<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class ChatsResult
{
    /** @param Chat[] $chats */
    public function __construct(
        public readonly array $chats,
        public readonly bool  $hasMore,
    ) {}

    public static function fromArray(array $d): self
    {
        $chats = array_map(
            static fn(array $c): Chat => Chat::fromArray($c),
            $d['chats'] ?? [],
        );
        return new self(
            chats:   $chats,
            hasMore: (bool) ($d['has_more'] ?? false),
        );
    }
}
