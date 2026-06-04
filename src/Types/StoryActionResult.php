<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class StoryActionResult
{
    public function __construct(
        public readonly bool $done = false,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            done: (bool) ($d['done'] ?? false),
        );
    }
}
