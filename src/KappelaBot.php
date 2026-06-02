<?php

declare(strict_types=1);

namespace Kappelas;

use Kappelas\Internal\HttpClient;
use Kappelas\Internal\WsClient;
use Kappelas\Resources\BotProfileResource;
use Kappelas\Resources\ChatsResource;
use Kappelas\Resources\CommunitiesResource;
use Kappelas\Resources\MessagesResource;
use Kappelas\Resources\WebhooksResource;
use Kappelas\Types\CallbackQuery;
use Kappelas\Types\Message;
use Kappelas\Types\SendResult;

final class KappelaBot
{
    public readonly MessagesResource   $messages;
    public readonly ChatsResource      $chats;
    public readonly WebhooksResource   $webhooks;
    public readonly BotProfileResource $profile;
    public readonly CommunitiesResource $communities;

    private WsClient $ws;

    /** @var callable|null */
    private $onMessage = null;
    /** @var callable|null */
    private $onCallbackQuery = null;
    /** @var callable|null */
    private $onConnected = null;
    /** @var callable|null */
    private $onDisconnected = null;
    /** @var callable|null */
    private $onError = null;

    public function __construct(
        private readonly string $token,
        string $baseUrl      = 'https://api.kappelas.com',
        int    $maxRetries   = 2,
        float  $timeout      = 30.0,
        int    $wsMaxRetries = 12,
    ) {
        // Bot authenticates via URL path: /v1/<token> — no auth header needed
        $base = '/v1/' . $token;
        $http = new HttpClient($baseUrl, $token, '', $maxRetries, $timeout);

        $this->messages = new MessagesResource($http, $base);
        $this->chats    = new ChatsResource($http, $base);
        $this->webhooks = new WebhooksResource($http, $base);
        $this->profile  = new BotProfileResource($http, $base);
        $this->communities = new CommunitiesResource($http, $base);

        $wsUrl    = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $baseUrl);
        $this->ws = new WsClient($wsUrl . $base . '/ws', '', '', $wsMaxRetries);
        $this->ws->onMessage(fn(array $payload) => $this->dispatch($payload));
        $this->ws->onConnected(function () { if ($this->onConnected !== null) ($this->onConnected)(); });
        $this->ws->onDisconnected(function (int $code, string $reason) {
            if ($this->onDisconnected !== null) ($this->onDisconnected)($code, $reason);
        });
        $this->ws->onError(function (\Throwable $e) { if ($this->onError !== null) ($this->onError)($e); });
    }

    public function onMessage(callable $fn): void        { $this->onMessage       = $fn; }
    public function onCallbackQuery(callable $fn): void  { $this->onCallbackQuery = $fn; }
    public function onConnected(callable $fn): void      { $this->onConnected     = $fn; }
    public function onDisconnected(callable $fn): void   { $this->onDisconnected  = $fn; }
    public function onError(callable $fn): void          { $this->onError         = $fn; }

    /**
     * Reply to a received message, injecting reply_to_id automatically.
     *
     * @param array{reply_markup?: array, delete_previous?: bool} $options
     */
    public function reply(Message $msg, string $text, array $options = []): SendResult
    {
        return $this->messages->send(array_merge([
            'chat_id'     => $msg->chatId,
            'text'        => $text,
            'reply_to_id' => $msg->id,
        ], $options));
    }

    /**
     * Start the WebSocket loop — this call blocks until stop() is called.
     */
    public function start(): void
    {
        $this->ws->run();
    }

    /**
     * Stop the WebSocket loop.
     */
    public function stop(): void
    {
        $this->ws->stop();
    }

    /**
     * Process a raw webhook payload (JSON-decoded array).
     * Use this in your HTTP handler instead of start().
     */
    public function handleWebhook(array $payload): void
    {
        $this->dispatch($payload);
    }

    private function dispatch(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? $payload;

        // Both 'callback_query' (WS) and 'callback' (webhook) are callback events
        $isCallback = $type === 'callback_query' || $type === 'callback'
            || isset($data['callback_data']);

        if ($isCallback) {
            if ($this->onCallbackQuery !== null) {
                ($this->onCallbackQuery)(CallbackQuery::fromArray($data));
            }
            return;
        }

        if ($this->onMessage !== null) {
            ($this->onMessage)(Message::fromArray($data));
        }
    }
}
