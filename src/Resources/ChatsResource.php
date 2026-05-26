<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\Chat;
use Kappelas\Types\ChatsResult;

final class ChatsResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    /**
     * Return a paginated list of chats.
     *
     * @param array{limit?: int, offset?: int} $params
     */
    public function list(array $params = []): ChatsResult
    {
        $path = $this->base . '/getChats';
        $qs   = [];
        if (!empty($params['limit'])) {
            $qs[] = 'limit=' . (int) $params['limit'];
        }
        if (!empty($params['offset'])) {
            $qs[] = 'offset=' . (int) $params['offset'];
        }
        if ($qs !== []) {
            $path .= '?' . implode('&', $qs);
        }
        return ChatsResult::fromArray($this->http->get($path));
    }

    /**
     * Iterate over every chat, handling pagination automatically.
     * Return false from $fn to stop early.
     *
     * @param callable(Chat): bool $fn
     */
    public function iterate(int $pageSize, callable $fn): void
    {
        if ($pageSize <= 0) {
            $pageSize = 50;
        }
        $offset = 0;
        while (true) {
            $result = $this->list(['limit' => $pageSize, 'offset' => $offset]);
            foreach ($result->chats as $chat) {
                if ($fn($chat) === false) {
                    return;
                }
            }
            if (!$result->hasMore || count($result->chats) === 0) {
                return;
            }
            $offset += count($result->chats);
        }
    }
}
