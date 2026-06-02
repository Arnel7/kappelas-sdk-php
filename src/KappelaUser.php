<?php

declare(strict_types=1);

namespace Kappelas;

use Kappelas\Internal\HttpClient;
use Kappelas\Internal\WsClient;
use Kappelas\Resources\ChatsResource;
use Kappelas\Resources\MessagesResource;
use Kappelas\Resources\UserProfileResource;
use Kappelas\Types\CallbackQuery;
use Kappelas\Types\Message;
use Kappelas\Types\SendResult;

final class KappelaUser
{
    public readonly MessagesResource    $messages;
    public readonly ChatsResource       $chats;
    public readonly UserProfileResource $profile;

    private HttpClient $http;
    private string $base;
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
        private readonly string $apiKey,
        string $baseUrl      = 'https://api.kappelas.com',
        int    $maxRetries   = 2,
        float  $timeout      = 30.0,
        int    $wsMaxRetries = 12,
    ) {
        $http = new HttpClient($baseUrl, $apiKey, 'X-Api-Key', $maxRetries, $timeout);

        $base = '/v1/me';
        $this->http = $http;
        $this->base = $base;
        $this->messages = new MessagesResource($http, $base);
        $this->chats    = new ChatsResource($http, $base);
        $this->profile  = new UserProfileResource($http, $base);

        // User WS auth via query string
        $wsUrl    = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $baseUrl);
        $this->ws = new WsClient($wsUrl . $base . '/ws?api_key=' . urlencode($apiKey), '', '', $wsMaxRetries);
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
     * Start the WebSocket loop — blocks until stop() is called.
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
     * Pause this account's personal automations.
     *
     * While paused, the account stops receiving incoming messages over /v1/me
     * (so an AI auto-responder is never triggered) and any send call is rejected
     * with AUTOMATIONS_PAUSED. Useful when the human owner takes over the chat.
     *
     * @return array{automations_paused: bool}
     */
    public function pauseAutomations(): array
    {
        return $this->http->post($this->base . '/pauseAutomations', []);
    }

    /**
     * Resume this account's personal automations after pauseAutomations().
     *
     * @return array{automations_paused: bool}
     */
    public function resumeAutomations(): array
    {
        return $this->http->post($this->base . '/resumeAutomations', []);
    }

    /**
     * Get whether this account's personal automations are currently paused.
     *
     * @return array{automations_paused: bool}
     */
    public function getAutomationStatus(): array
    {
        return $this->http->post($this->base . '/getAutomationStatus', []);
    }

    /**
     * Pause your personal automations in ONE conversation only.
     *
     * Use this to take over a single chat (e.g. you start replying to X yourself):
     * your AI stops receiving messages from that conversation while it keeps handling
     * all your other chats. Unlike pauseAutomations(), this is scoped to one chat.
     *
     * @return array{done: bool}
     */
    public function pauseAutomationInChat(int $chatId): array
    {
        return $this->http->post($this->base . '/pauseAutomationInChat', ['chat_id' => $chatId]);
    }

    /**
     * Resume your personal automations in a conversation.
     *
     * @return array{done: bool}
     */
    public function resumeAutomationInChat(int $chatId): array
    {
        return $this->http->post($this->base . '/resumeAutomationInChat', ['chat_id' => $chatId]);
    }

    private function dispatch(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? $payload;

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
