<?php

declare(strict_types=1);

namespace Kappelas\Internal;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Kappelas\KappelaError;
use Psr\Http\Message\ResponseInterface;

/** @internal */
final class HttpClient
{
    private Client $guzzle;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $authHeader,  // empty string = no auth header (bot auth via URL path)
        int $maxRetries = 2,
        float $timeout  = 30.0,
    ) {
        $stack = HandlerStack::create();
        $stack->push($this->retryMiddleware($maxRetries));

        $this->guzzle = new Client([
            'handler'         => $stack,
            'timeout'         => $timeout,
            'connect_timeout' => 10.0,
            'http_errors'     => false,   // let decode() convert 4xx/5xx to KappelaError
        ]);
    }

    public function get(string $path): array
    {
        $response = $this->guzzle->get($this->baseUrl . $path, [
            'headers' => $this->headers(),
        ]);
        return $this->decode($response);
    }

    public function post(string $path, array $body): array
    {
        $response = $this->guzzle->post($this->baseUrl . $path, [
            'headers' => array_merge($this->headers(), ['Content-Type' => 'application/json']),
            'body'    => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        return $this->decode($response);
    }

    public function postMultipart(string $path, array $fields, array $file): array
    {
        $multipart = [];
        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }
            $multipart[] = ['name' => $name, 'contents' => (string) $value];
        }
        $multipart[] = [
            'name'     => 'file',
            'contents' => $file['data'],
            'filename' => $file['filename'],
            'headers'  => ['Content-Type' => $file['content_type']],
        ];

        $response = $this->guzzle->post($this->baseUrl . $path, [
            'headers'   => $this->headers(),
            'multipart' => $multipart,
        ]);
        return $this->decode($response);
    }

    public function delete(string $path): array
    {
        $response = $this->guzzle->delete($this->baseUrl . $path, [
            'headers' => $this->headers(),
        ]);
        return $this->decode($response);
    }

    /**
     * Download raw bytes from an absolute URL (e.g. a short-lived signed
     * download URL). No auth header is sent — the signature is in the URL.
     */
    public function download(string $url): string
    {
        $response = $this->guzzle->get($url);
        return (string) $response->getBody();
    }

    private function headers(): array
    {
        if ($this->authHeader === '') {
            return [];
        }
        return [$this->authHeader => $this->token];
    }

    private function decode(ResponseInterface $response): array
    {
        $body      = (string) $response->getBody();
        $status    = $response->getStatusCode();
        $requestId = $response->getHeaderLine('X-Request-Id') ?: null;

        // Decode JSON; on non-JSON error bodies synthesize a KappelaError with the raw body
        $data = json_decode($body, true, 512);
        if ($status >= 400) {
            if (!is_array($data)) {
                throw new KappelaError(
                    errorMessage: "HTTP $status — " . substr($body, 0, 200),
                    errorCode:    KappelaError::INTERNAL_ERROR,
                    status:       $status,
                    requestId:    $requestId,
                );
            }
            // API error fields may be under 'error'/'error_code' or 'message'/'code'
            $errBody = [
                'message' => $data['error']      ?? $data['message'] ?? 'Unknown error',
                'code'    => $data['error_code'] ?? $data['code']    ?? KappelaError::INTERNAL_ERROR,
            ];
            throw KappelaError::fromArray($errBody, $status, $requestId);
        }
        if ($data === null) {
            throw new \RuntimeException("Invalid JSON response: " . substr($body, 0, 200));
        }
        // API wraps successful responses in {"ok": true, "result": {...}}
        return $data['result'] ?? $data;
    }

    private function retryMiddleware(int $maxRetries): callable
    {
        $delays = [1000, 2000, 4000];

        return Middleware::retry(
            decider: static function (int $retries, $request, ?ResponseInterface $response = null, $exception = null) use ($maxRetries): bool {
                if ($retries >= $maxRetries) {
                    return false;
                }
                if ($exception instanceof RequestException) {
                    return true;
                }
                if ($response !== null) {
                    $status = $response->getStatusCode();
                    return $status === 429 || $status >= 500;
                }
                return false;
            },
            delay: static function (int $retries) use ($delays): int {
                return $delays[min($retries - 1, count($delays) - 1)];
            },
        );
    }
}
