<?php

declare(strict_types=1);

namespace Kappelas\Resources;

use Kappelas\Internal\HttpClient;
use Kappelas\Types\WebhookDeleteResult;
use Kappelas\Types\WebhookInfo;
use Kappelas\Types\WebhookSetResult;

final class WebhooksResource
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string     $base,
    ) {}

    /**
     * Register a webhook URL.
     *
     * @param array{url: string} $params
     */
    public function set(array $params): WebhookSetResult
    {
        return WebhookSetResult::fromArray(
            $this->http->post($this->base . '/setWebhook', ['url' => $params['url']])
        );
    }

    /**
     * Get current webhook configuration.
     */
    public function getInfo(): WebhookInfo
    {
        return WebhookInfo::fromArray($this->http->get($this->base . '/getWebhookInfo'));
    }

    /**
     * Remove the active webhook.
     */
    public function delete(): WebhookDeleteResult
    {
        return WebhookDeleteResult::fromArray(
            $this->http->post($this->base . '/deleteWebhook', [])
        );
    }
}
