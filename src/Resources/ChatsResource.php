<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\AddChatMemberResult;
use Kappelas\Types\BanChatMemberResult;
use Kappelas\Types\Chat;
use Kappelas\Types\ChatInviteLink;
use Kappelas\Types\ChatMemberInfo;
use Kappelas\Types\ChatsResult;
use Kappelas\Types\GetChatAdministratorsResult;
use Kappelas\Types\GetChatInviteLinksResult;
use Kappelas\Types\GetMyGroupsResult;
use Kappelas\Types\LeaveChatResult;
use Kappelas\Types\PromoteChatMemberResult;
use Kappelas\Types\RevokeChatInviteLinkResult;

final class ChatsResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    // ─── Listing ─────────────────────────────────────────────────────────────

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

    /**
     * Return all groups and channels the bot belongs to.
     */
    public function getMyGroups(): GetMyGroupsResult
    {
        $raw = $this->http->post($this->base . '/getMyGroups', []);
        // API may return the array directly or wrapped in {'groups': [...]}
        if (isset($raw[0]) || $raw === []) {
            return GetMyGroupsResult::fromArray(['groups' => $raw]);
        }
        return GetMyGroupsResult::fromArray($raw);
    }

    // ─── Member management ────────────────────────────────────────────────────

    /**
     * Add a user to a group or channel (admin only).
     *
     * @param array{chat_id: int, user_id: string|int} $params
     */
    public function addMember(array $params): AddChatMemberResult
    {
        return AddChatMemberResult::fromArray($this->http->post($this->base . '/addChatMember', [
            'chat_id' => $params['chat_id'],
            'user_id' => (string) $params['user_id'],
        ]));
    }

    /**
     * Ban a user from a group or channel (admin only).
     *
     * @param array{chat_id: int, user_id: string|int} $params
     */
    public function banMember(array $params): BanChatMemberResult
    {
        return BanChatMemberResult::fromArray($this->http->post($this->base . '/banChatMember', [
            'chat_id' => $params['chat_id'],
            'user_id' => (string) $params['user_id'],
        ]));
    }

    /**
     * Make the bot leave a group or channel.
     *
     * @param array{chat_id: int} $params
     */
    public function leaveChat(array $params): LeaveChatResult
    {
        return LeaveChatResult::fromArray($this->http->post($this->base . '/leaveChat', [
            'chat_id' => $params['chat_id'],
        ]));
    }

    /**
     * Promote or demote a member (admin only).
     *
     * @param array{chat_id: int, user_id: string|int, role: 'member'|'admin'} $params
     */
    public function promoteMember(array $params): PromoteChatMemberResult
    {
        return PromoteChatMemberResult::fromArray($this->http->post($this->base . '/promoteChatMember', [
            'chat_id' => $params['chat_id'],
            'user_id' => (string) $params['user_id'],
            'role'    => $params['role'],
        ]));
    }

    /**
     * Get all administrators of a group or channel.
     *
     * @param array{chat_id: int} $params
     */
    public function getAdministrators(array $params): GetChatAdministratorsResult
    {
        $raw = $this->http->post($this->base . '/getChatAdministrators', [
            'chat_id' => $params['chat_id'],
        ]);
        // API returns the admins array directly as result
        if (isset($raw[0]) || $raw === []) {
            return GetChatAdministratorsResult::fromArray(['admins' => $raw]);
        }
        return GetChatAdministratorsResult::fromArray($raw);
    }

    /**
     * Get info about a single member.
     *
     * @param array{chat_id: int, user_id: string|int} $params
     */
    public function getMember(array $params): ChatMemberInfo
    {
        $raw = $this->http->post($this->base . '/getChatMember', [
            'chat_id' => $params['chat_id'],
            'user_id' => (string) $params['user_id'],
        ]);
        return ChatMemberInfo::fromArray($raw);
    }

    // ─── Invite links ─────────────────────────────────────────────────────────

    /**
     * Create an invite link (admin only).
     *
     * @param array{chat_id: int, max_uses?: int, expires_in?: string} $params  expires_in: "1h"|"24h"|"7d"|"30d"
     */
    public function createInviteLink(array $params): ChatInviteLink
    {
        $body = ['chat_id' => $params['chat_id']];
        if (isset($params['max_uses']))  $body['max_uses']  = $params['max_uses'];
        if (isset($params['expires_in'])) $body['expires_in'] = $params['expires_in'];
        return ChatInviteLink::fromArray($this->http->post($this->base . '/createChatInviteLink', $body));
    }

    /**
     * Create a single-use invite link (max_uses=1).
     *
     * @param array{chat_id: int, expires_in?: string} $params  expires_in: "1h"|"24h"|"7d"|"30d"
     */
    public function createSingleUseInviteLink(array $params): ChatInviteLink
    {
        $body = ['chat_id' => $params['chat_id'], 'max_uses' => 1];
        if (isset($params['expires_in'])) $body['expires_in'] = $params['expires_in'];
        return ChatInviteLink::fromArray($this->http->post($this->base . '/createChatInviteLink', $body));
    }

    /**
     * List all active invite links for a chat (admin only).
     *
     * @param array{chat_id: int} $params
     */
    public function getInviteLinks(array $params): GetChatInviteLinksResult
    {
        $raw = $this->http->post($this->base . '/getChatInviteLinks', [
            'chat_id' => $params['chat_id'],
        ]);
        return GetChatInviteLinksResult::fromArray($raw);
    }

    /**
     * Revoke an invite link by code (admin only).
     *
     * @param array{chat_id: int, code: string} $params
     */
    public function revokeInviteLink(array $params): RevokeChatInviteLinkResult
    {
        return RevokeChatInviteLinkResult::fromArray($this->http->post($this->base . '/revokeChatInviteLink', [
            'chat_id' => $params['chat_id'],
            'code'    => $params['code'],
        ]));
    }
}
