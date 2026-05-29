<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class AddChatMemberResult
{
    public function __construct(
        public readonly string $description = '',
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(description: (string) ($d['description'] ?? ''));
    }
}
