<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class StoryMediaUpload
{
    public function __construct(
        public readonly string $mediaId,
        public readonly string $url,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?string $mediumUrl = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            mediaId: (string) ($d['media_id'] ?? ''),
            url: (string) ($d['url'] ?? ''),
            width: isset($d['width']) ? (int) $d['width'] : null,
            height: isset($d['height']) ? (int) $d['height'] : null,
            thumbnailUrl: $d['thumbnail_url'] ?? null,
            mediumUrl: $d['medium_url'] ?? null,
        );
    }
}
