<?php

declare(strict_types=1);

namespace Kappelas\Types;

final class GetChatAdministratorsResult
{
    /** @param ChatMemberInfo[] $admins */
    public function __construct(
        public readonly array $admins,
    ) {}

    public static function fromArray(array $d): self
    {
        $admins = array_map(
            static fn(array $a): ChatMemberInfo => ChatMemberInfo::fromArray($a),
            $d['admins'] ?? [],
        );
        return new self(admins: $admins);
    }
}
