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
        string $token      = '',   // unused — auth is embedded in $wsUrl
        string $authHeader = '',   // unused — kept for API compatibility
        private readonly int $maxRetries = 12,
    ) {}

    public function onMessage(callable $fn): void      { $this->onMessage      = $fn; }
    public function onConnected(callable $fn): void    { $this->onConnected    = $fn; }
    public function onDisconnected(callable $fn): void { $this->onDisconnected = $fn; }
    public function onError(callable $fn): void        { $this->onError        = $fn; }

    public function run(): void
    {
        $this->running = true;
        $attempt       = 0;

        while ($this->running) {
            try {
                $this->socket = new WsSocket($this->wsUrl, [
                    'timeout'       => 30,
                    'persistent'    => false,
                    'fragment_size' => 4096,
                ]);

                // Reset backoff on every successful connection
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
                        if (!is_array($data)) {
                            // Malformed frame — surface via onError instead of silently dropping
                            if ($this->onError !== null) {
                                ($this->onError)(new \RuntimeException("Received non-JSON WebSocket frame: $raw"));
                            }
                            continue;
                        }
                        if ($this->onMessage !== null) {
                            ($this->onMessage)($data);
                        }
                    } catch (ConnectionException $e) {
                        break;
                    }
                }

                $code = $this->socket->getCloseStatus() ?? 0;
                if ($this->onDisconnected !== null) {
                    ($this->onDisconnected)($code, '');
                }
            } catch (Throwable $e) {
                if ($this->onError !== null) {
                    ($this->onError)($e);
                }
                // Also fire onDisconnected so callers can track state
                if ($this->onDisconnected !== null) {
                    ($this->onDisconnected)(0, $e->getMessage());
                }
            } finally {
                $this->socket = null;
            }

            if (!$this->running) {
                break;
            }

            $attempt++;
            // maxRetries=0 → no reconnects at all; use >= so the limit is exact
            if ($this->maxRetries === 0 || $attempt >= $this->maxRetries) {
                if ($this->onError !== null) {
                    ($this->onError)(new \RuntimeException("WebSocket max reconnect attempts ({$this->maxRetries}) reached"));
                }
                break;
            }

            // Exponential backoff; use short sleeps so stop() is responsive
            $remaining = min(1 << ($attempt - 1), 30);
            while ($remaining > 0 && $this->running) {
                sleep(1);
                $remaining--;
            }
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
