<?php

declare(strict_types=1);

namespace Kappelas\Internal;

use Throwable;
use WebSocket\Client as WsSocket;
use WebSocket\ConnectionException;

/** @internal */
final class WsClient
{
    private ?WsSocket $socket = null;
    private bool $running     = false;

    /** @var callable|null */
    private $onMessage = null;
    /** @var callable|null */
    private $onConnected = null;
    /** @var callable|null */
    private $onDisconnected = null;
    /** @var callable|null */
    private $onError = null;

    public function __construct(
        private readonly string $wsUrl,
        private readonly string $token,
        private readonly string $authHeader,
        private readonly int $maxRetries = 12,
    ) {}

    public function onMessage(callable $fn): void   { $this->onMessage     = $fn; }
    public function onConnected(callable $fn): void { $this->onConnected   = $fn; }
    public function onDisconnected(callable $fn): void { $this->onDisconnected = $fn; }
    public function onError(callable $fn): void     { $this->onError       = $fn; }

    public function run(): void
    {
        $this->running = true;
        $attempt       = 0;

        while ($this->running) {
            try {
                $this->socket = new WsSocket($this->wsUrl, [
                    'headers'         => [$this->authHeader => $this->token],
                    'timeout'         => 30,
                    'persistent'      => false,
                    'fragment_size'   => 4096,
                ]);

                $attempt = 0;
                if ($this->onConnected !== null) {
                    ($this->onConnected)();
                }

                while ($this->running) {
                    try {
                        $raw = $this->socket->receive();
                        if ($raw === null) {
                            break;
                        }
                        $data = json_decode($raw, true);
                        if (is_array($data) && $this->onMessage !== null) {
                            ($this->onMessage)($data);
                        }
                    } catch (ConnectionException $e) {
                        break;
                    }
                }

                $code   = $this->socket->getCloseStatus() ?? 0;
                $reason = '';
                if ($this->onDisconnected !== null) {
                    ($this->onDisconnected)($code, $reason);
                }
            } catch (Throwable $e) {
                if ($this->onError !== null) {
                    ($this->onError)($e);
                }
            } finally {
                $this->socket = null;
            }

            if (!$this->running) {
                break;
            }

            $attempt++;
            if ($this->maxRetries > 0 && $attempt > $this->maxRetries) {
                if ($this->onError !== null) {
                    ($this->onError)(new \RuntimeException("WebSocket max reconnect attempts ({$this->maxRetries}) reached"));
                }
                break;
            }

            $delay = min(1 << ($attempt - 1), 30);
            sleep($delay);
        }
    }

    public function stop(): void
    {
        $this->running = false;
        if ($this->socket !== null) {
            try {
                $this->socket->close();
            } catch (Throwable) {
            }
            $this->socket = null;
        }
    }
}
