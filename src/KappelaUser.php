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

final class KappelaUser
{
    public readonly MessagesResource   $messages;
    public readonly ChatsResource      $chats;
    public readonly UserProfileResource $profile;

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

    private function dispatch(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? $payload;

        $isCallback = $type === 'callback_query'
            || ($type === null && isset($data['callback_data']));

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
