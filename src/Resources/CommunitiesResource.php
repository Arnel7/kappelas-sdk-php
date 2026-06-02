<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\Community;
use Kappelas\Types\CommunityActionResult;
use Kappelas\Types\CommunityDetail;
use Kappelas\Types\CommunityGroupRequest;
use Kappelas\Types\CommunityInvite;
use Kappelas\Types\CommunityInvitePreview;
use Kappelas\Types\CommunityJoinRequest;

/**
 * Gestion des communautés par un bot (membres, rôles, invites, demandes).
 *
 * Un bot administre une communauté seulement s'il en est admin. Pour rendre quelqu'un
 * (personne OU bot) admin : on l'ajoute d'abord membre, puis on le promeut.
 *
 *   $bot->communities->addMember(['community_id' => 7, 'user_id' => 'uuid', 'role' => 'member']);
 *   $bot->communities->promoteMember(['community_id' => 7, 'user_id' => 'uuid', 'role' => 'admin']);
 *
 * ⚠️ `role` est le rôle DANS LA COMMUNAUTÉ (distinct du rôle dans un groupe rattaché).
 */
final class CommunitiesResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    // ─── Lecture / CRUD ───────────────────────────────────────────────────────

    /** @return Community[] Communautés du bot (chacune avec son `role`). */
    public function list(): array
    {
        $raw = $this->http->post($this->base . '/getMyCommunities', []);
        return array_map([Community::class, 'fromArray'], $raw['communities'] ?? []);
    }

    /** @return Community[] Communautés où le bot est admin (filtre role === 'admin'). */
    public function listAdmin(): array
    {
        return array_values(array_filter($this->list(), static fn (Community $c) => $c->role === 'admin'));
    }

    /** @param array{community_id: int} $params */
    public function get(array $params): CommunityDetail
    {
        return CommunityDetail::fromArray($this->http->post($this->base . '/getCommunity', [
            'community_id' => (int) $params['community_id'],
        ]));
    }

    /** @param array{name: string, description?: string, avatar_url?: string, requires_approval?: bool} $params */
    public function create(array $params): Community
    {
        $body = ['name' => (string) $params['name']];
        foreach (['description', 'avatar_url', 'requires_approval'] as $k) {
            if (array_key_exists($k, $params)) {
                $body[$k] = $params[$k];
            }
        }
        return Community::fromArray($this->http->post($this->base . '/createCommunity', $body));
    }

    /**
     * Modifie une communauté (admin). Seuls les champs fournis sont envoyés.
     * @param array{community_id: int, name?: string, description?: ?string, avatar_url?: ?string, announcement_channel_id?: ?int, requires_approval?: bool} $params
     */
    public function update(array $params): Community
    {
        $body = ['community_id' => (int) $params['community_id']];
        foreach (['name', 'description', 'avatar_url', 'announcement_channel_id', 'requires_approval'] as $k) {
            if (array_key_exists($k, $params)) {
                $body[$k] = $params[$k];
            }
        }
        return Community::fromArray($this->http->post($this->base . '/updateCommunity', $body));
    }

    /** @param array{community_id: int} $params */
    public function delete(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/deleteCommunity', [
            'community_id' => (int) $params['community_id'],
        ]));
    }

    /** Rejoindre une communauté. `pending === true` si adhésion sur autorisation. @param array{community_id: int} $params */
    public function join(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/joinCommunity', [
            'community_id' => (int) $params['community_id'],
        ]));
    }

    // ─── Membres ──────────────────────────────────────────────────────────────

    /** @param array{community_id: int, user_id: string, role?: 'member'|'admin'} $params */
    public function addMember(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/addCommunityMember', [
            'community_id' => (int) $params['community_id'],
            'user_id'      => (string) $params['user_id'],
            'role'         => $params['role'] ?? 'member',
        ]));
    }

    /** @param array{community_id: int, user_id: string, role: 'member'|'admin'} $params */
    public function promoteMember(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/promoteCommunityMember', [
            'community_id' => (int) $params['community_id'],
            'user_id'      => (string) $params['user_id'],
            'role'         => $params['role'],
        ]));
    }

    /** @param array{community_id: int, user_id: string} $params */
    public function banMember(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/banCommunityMember', [
            'community_id' => (int) $params['community_id'],
            'user_id'      => (string) $params['user_id'],
        ]));
    }

    /** @param array{community_id: int} $params */
    public function leave(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/leaveCommunity', [
            'community_id' => (int) $params['community_id'],
        ]));
    }

    // ─── Liens d'invitation ───────────────────────────────────────────────────

    /** @param array{community_id: int, max_uses?: int, expires_in?: string} $params */
    public function createInviteLink(array $params): CommunityInvite
    {
        $body = ['community_id' => (int) $params['community_id']];
        if (isset($params['max_uses']))   $body['max_uses']   = (int) $params['max_uses'];
        if (isset($params['expires_in'])) $body['expires_in'] = (string) $params['expires_in'];
        return CommunityInvite::fromArray($this->http->post($this->base . '/createCommunityInviteLink', $body));
    }

    /** @param array{community_id: int} $params @return CommunityInvite[] */
    public function getInviteLinks(array $params): array
    {
        $raw = $this->http->post($this->base . '/getCommunityInviteLinks', [
            'community_id' => (int) $params['community_id'],
        ]);
        return array_map([CommunityInvite::class, 'fromArray'], $raw['invites'] ?? []);
    }

    /** @param array{community_id: int, code: string} $params */
    public function revokeInviteLink(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/revokeCommunityInviteLink', [
            'community_id' => (int) $params['community_id'],
            'code'         => (string) $params['code'],
        ]));
    }

    /** @param array{code: string} $params */
    public function previewInvite(array $params): CommunityInvitePreview
    {
        return CommunityInvitePreview::fromArray($this->http->post($this->base . '/previewCommunityInvite', [
            'code' => (string) $params['code'],
        ]));
    }

    /** Le bot rejoint une communauté via un code. @param array{code: string} $params @return int community_id */
    public function acceptInvite(array $params): int
    {
        $raw = $this->http->post($this->base . '/acceptCommunityInvite', ['code' => (string) $params['code']]);
        return (int) ($raw['community_id'] ?? 0);
    }

    // ─── Demandes d'adhésion (user → communauté) ──────────────────────────────

    /** @param array{community_id: int} $params @return CommunityJoinRequest[] */
    public function getJoinRequests(array $params): array
    {
        $raw = $this->http->post($this->base . '/getCommunityJoinRequests', [
            'community_id' => (int) $params['community_id'],
        ]);
        return array_map([CommunityJoinRequest::class, 'fromArray'], $raw ?? []);
    }

    /** @param array{community_id: int, request_id: int} $params */
    public function approveJoinRequest(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/approveCommunityJoinRequest', [
            'community_id' => (int) $params['community_id'],
            'request_id'   => (int) $params['request_id'],
        ]));
    }

    /** @param array{community_id: int, request_id: int} $params */
    public function rejectJoinRequest(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/rejectCommunityJoinRequest', [
            'community_id' => (int) $params['community_id'],
            'request_id'   => (int) $params['request_id'],
        ]));
    }

    // ─── Demandes de groupe + liaison de groupes ──────────────────────────────

    /** @param array{community_id: int} $params @return CommunityGroupRequest[] */
    public function getGroupRequests(array $params): array
    {
        $raw = $this->http->post($this->base . '/getCommunityGroupRequests', [
            'community_id' => (int) $params['community_id'],
        ]);
        return array_map([CommunityGroupRequest::class, 'fromArray'], $raw ?? []);
    }

    /** @param array{community_id: int, request_id: int} $params */
    public function approveGroupRequest(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/approveCommunityGroupRequest', [
            'community_id' => (int) $params['community_id'],
            'request_id'   => (int) $params['request_id'],
        ]));
    }

    /** @param array{community_id: int, request_id: int} $params */
    public function rejectGroupRequest(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/rejectCommunityGroupRequest', [
            'community_id' => (int) $params['community_id'],
            'request_id'   => (int) $params['request_id'],
        ]));
    }

    /** @param array{community_id: int, conversation_id: int} $params */
    public function addGroup(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/addCommunityGroup', [
            'community_id'    => (int) $params['community_id'],
            'conversation_id' => (int) $params['conversation_id'],
        ]));
    }

    /** @param array{community_id: int, conversation_id: int} $params */
    public function removeGroup(array $params): CommunityActionResult
    {
        return CommunityActionResult::fromArray($this->http->post($this->base . '/removeCommunityGroup', [
            'community_id'    => (int) $params['community_id'],
            'conversation_id' => (int) $params['conversation_id'],
        ]));
    }
}
