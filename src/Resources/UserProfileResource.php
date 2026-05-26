<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\UserProfile;

final class UserProfileResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    public function get(): UserProfile
    {
        return UserProfile::fromArray($this->http->get($this->base . '/getMe'));
    }
}
