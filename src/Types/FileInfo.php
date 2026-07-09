<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class FileInfo
{
    public function __construct(
        public readonly string  $mediaId,
        public readonly string  $url,
        public readonly ?string $filename    = null,
        public readonly ?string $contentType = null,
        public readonly ?int    $sizeBytes   = null,
        public readonly ?int    $expiresIn   = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            mediaId:     (string) ($d['media_id'] ?? ''),
            url:         (string) ($d['url'] ?? ''),
            filename:    $d['filename']     ?? null,
            contentType: $d['content_type'] ?? null,
            sizeBytes:   isset($d['size_bytes']) ? (int) $d['size_bytes'] : null,
            expiresIn:   isset($d['expires_in']) ? (int) $d['expires_in'] : null,
        );
    }
}
