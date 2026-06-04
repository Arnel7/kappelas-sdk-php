<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class StoryPreferences
{
    /** @param string[] $audienceUserIds */
    public function __construct(
        public readonly string $audience,
        public readonly array $audienceUserIds = [],
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            audience: (string) ($d['audience'] ?? 'all'),
            audienceUserIds: $d['audience_user_ids'] ?? [],
        );
    }
}
