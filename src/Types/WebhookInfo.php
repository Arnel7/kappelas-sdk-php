<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class WebhookInfo
{
    public function __construct(
        public readonly bool    $active,
        public readonly ?string $url       = null,
        public readonly ?int    $createdAt = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            active:    (bool) ($d['active']     ?? false),
            url:       $d['url']                ?? null,
            createdAt: isset($d['created_at']) ? (int) $d['created_at'] : null,
        );
    }
}
