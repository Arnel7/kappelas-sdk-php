<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\Story;
use Kappelas\Types\StoryActionResult;
use Kappelas\Types\StoryMediaUpload;
use Kappelas\Types\StoryPreferences;
use Kappelas\Types\StoryView;

/**
 * Stories (éphémères 24 h) — réservé aux comptes utilisateur (`$me->stories`).
 *
 * L'audience est basée sur vos contacts en conversation privée. Pour une story
 * image/vidéo, le SDK uploade le fichier automatiquement (comme `messages->sendPhoto`)
 * puis crée la story. Pour text/poll, aucun upload.
 */
final class StoriesResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    /**
     * Créer une story.
     *
     * Pour `image`/`video` : fournir `media` (uploadé automatiquement) ou un `media_id`
     * déjà uploadé. Pour `text`/`poll` : juste `caption`.
     *
     * `link` (+ optional `link_label`) attaches a clickable CTA link shown over the
     * story in the Kappela apps.
     *
     * @param array{
     *   type: string,
     *   media?: array{data: string, filename: string, content_type: string}|string,
     *   media_id?: string,
     *   caption?: string,
     *   link?: string,
     *   link_label?: string,
     *   audience?: string,
     *   audience_user_ids?: string[],
     * } $params
     */
    public function create(array $params): Story
    {
        $type    = (string) $params['type'];
        $mediaId = $params['media_id'] ?? null;

        if (($type === 'image' || $type === 'video') && ($mediaId === null || $mediaId === '')) {
            if (!isset($params['media'])) {
                throw new \InvalidArgumentException("create: 'media' or 'media_id' is required for image/video stories");
            }
            $mediaId = $this->uploadMedia($params['media'])->mediaId;
        }

        $body = ['media_type' => $type];
        if ($mediaId !== null && $mediaId !== '') {
            $body['media_id'] = $mediaId;
        }

        // Le lien CTA est porté dans la caption en JSON ({text, link, linkLabel}) —
        // format lu par les apps Kappela (pas de champ backend dédié).
        if (!empty($params['link'])) {
            $env = ['text' => (string) ($params['caption'] ?? ''), 'link' => (string) $params['link']];
            if (!empty($params['link_label'])) {
                $env['linkLabel'] = (string) $params['link_label'];
            }
            $body['caption'] = json_encode($env, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif (array_key_exists('caption', $params)) {
            $body['caption'] = $params['caption'];
        }
        foreach (['audience', 'audience_user_ids'] as $k) {
            if (array_key_exists($k, $params)) {
                $body[$k] = $params[$k];
            }
        }
        return Story::fromArray($this->http->post($this->base . '/createStory', $body));
    }

    /**
     * Uploader le média d'une story (image/vidéo). Généralement inutile d'appeler
     * directement — `create(['media' => ...])` le fait.
     *
     * @param array{data: string, filename: string, content_type: string}|string $file
     */
    public function uploadMedia(array|string $file): StoryMediaUpload
    {
        if (is_string($file)) {
            $file = [
                'data'         => file_get_contents($file),
                'filename'     => basename($file),
                'content_type' => mime_content_type($file) ?: 'application/octet-stream',
            ];
        }
        return StoryMediaUpload::fromArray($this->http->postMultipart($this->base . '/uploadStoryMedia', [], $file));
    }

    /** @return Story[] Feed des stories actives de vos contacts. */
    public function list(): array
    {
        $raw = $this->http->post($this->base . '/getStories', []);
        return array_map([Story::class, 'fromArray'], $raw);
    }

    /** @return Story[] Vos propres stories. */
    public function listMine(): array
    {
        $raw = $this->http->post($this->base . '/getMyStories', []);
        return array_map([Story::class, 'fromArray'], $raw);
    }

    /** Une story par id (audience vérifiée côté serveur). */
    public function get(string $storyId): Story
    {
        return Story::fromArray($this->http->post($this->base . '/getStory', ['story_id' => $storyId]));
    }

    /** Supprimer une de vos stories. */
    public function delete(string $storyId): StoryActionResult
    {
        return StoryActionResult::fromArray($this->http->post($this->base . '/deleteStory', ['story_id' => $storyId]));
    }

    /** Marquer une story comme vue. */
    public function view(string $storyId): StoryActionResult
    {
        return StoryActionResult::fromArray($this->http->post($this->base . '/viewStory', ['story_id' => $storyId]));
    }

    /** @return StoryView[] Qui a vu une de vos stories (propriétaire uniquement). */
    public function getViewers(string $storyId): array
    {
        $raw = $this->http->post($this->base . '/getStoryViewers', ['story_id' => $storyId]);
        return array_map([StoryView::class, 'fromArray'], $raw);
    }

    /** Préférence d'audience par défaut. */
    public function getPreferences(): StoryPreferences
    {
        return StoryPreferences::fromArray($this->http->post($this->base . '/getStoryPreferences', []));
    }

    /**
     * Définir la préférence d'audience par défaut.
     *
     * @param string[] $audienceUserIds
     */
    public function setPreferences(string $audience, array $audienceUserIds = []): StoryActionResult
    {
        return StoryActionResult::fromArray($this->http->post($this->base . '/setStoryPreferences', [
            'audience'          => $audience,
            'audience_user_ids' => $audienceUserIds,
        ]));
    }
}
