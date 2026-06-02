<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityDetail
{
    public function __construct(
        public readonly Community $community,
        /** @var CommunityGroup[] */
        public readonly array $groups,
        /** @var CommunityMember[] */
        public readonly array $members,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            community: Community::fromArray($d['community'] ?? []),
            groups: array_map([CommunityGroup::class, 'fromArray'], $d['groups'] ?? []),
            members: array_map([CommunityMember::class, 'fromArray'], $d['members'] ?? []),
        );
    }
}
