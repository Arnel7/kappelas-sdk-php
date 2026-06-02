<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class CommunityActionResult
{
    public function __construct(
        public readonly bool $done = false,
        public readonly bool $pending = false,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            done: (bool) ($d['done'] ?? false),
            pending: (bool) ($d['pending'] ?? false),
        );
    }
}
