<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class DeleteResult
{
    public function __construct(
        public readonly bool $deleted,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            deleted: (bool) ($d['deleted'] ?? false),
        );
    }
}
