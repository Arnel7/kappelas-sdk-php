<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\BotProfile;

final class BotProfileResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    public function get(): BotProfile
    {
        return BotProfile::fromArray($this->http->get($this->base . '/getMe'));
    }
}
