<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class WebhookSetResult
{
    public function __construct(
        public readonly bool    $active,
        public readonly ?string $url = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            active: (bool) ($d['active'] ?? false),
            url:    $d['url'] ?? null,
        );
    }
}
