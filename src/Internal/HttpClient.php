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
        private readonly string $authHeader,
        int $maxRetries = 2,
        float $timeout  = 30.0,
    ) {
        $stack = HandlerStack::create();
        $stack->push($this->retryMiddleware($maxRetries));

        $this->guzzle = new Client([
            'handler'         => $stack,
            'timeout'         => $timeout,
            'connect_timeout' => 10.0,
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

    private function headers(): array
    {
        return [$this->authHeader => $this->token];
    }

    private function decode(ResponseInterface $response): array
    {
        $body   = (string) $response->getBody();
        $status = $response->getStatusCode();
        $data   = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if ($status >= 400) {
            $requestId = $response->getHeaderLine('X-Request-Id') ?: null;
            throw KappelaError::fromArray($data, $status, $requestId ?: null);
        }
        return $data;
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
