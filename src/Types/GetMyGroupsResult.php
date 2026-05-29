<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class GetMyGroupsResult
{
    /** @param BotGroupEntry[] $groups */
    public function __construct(
        public readonly array $groups,
    ) {}

    public static function fromArray(array $d): self
    {
        $groups = array_map(
            static fn(array $g): BotGroupEntry => BotGroupEntry::fromArray($g),
            $d['groups'] ?? [],
        );
        return new self(groups: $groups);
    }
}
