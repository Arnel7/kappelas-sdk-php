<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class WebhookDeleteResult
{
    public function __construct(
        public readonly bool $active,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(active: (bool) ($d['active'] ?? false));
    }
}
