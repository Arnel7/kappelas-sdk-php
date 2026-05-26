<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class TypingResult
{
    public function __construct(
        public readonly bool $ok,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(ok: (bool) ($d['ok'] ?? true));
    }
}
