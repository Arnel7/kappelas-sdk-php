<?php

declare(strict_types=1);

namespace Kappelas;

use RuntimeException;

class KappelaError extends RuntimeException
{
    public const UNAUTHORIZED        = 'UNAUTHORIZED';
    public const FORBIDDEN           = 'FORBIDDEN';
    public const NOT_FOUND           = 'NOT_FOUND';
    public const MISSING_FIELD       = 'MISSING_FIELD';
    public const INVALID_FIELD       = 'INVALID_FIELD';
    public const CONFLICT            = 'CONFLICT';
    public const METHOD_NOT_ALLOWED  = 'METHOD_NOT_ALLOWED';
    public const INVALID_PATH        = 'INVALID_PATH';
    public const INTERNAL_ERROR      = 'INTERNAL_ERROR';
    public const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    public const UPSTREAM_ERROR      = 'UPSTREAM_ERROR';

    private static array $hints = [
        self::UNAUTHORIZED        => ['Token or API key invalid or expired.', 'Regenerate your token from BotMother or your account settings.'],
        self::FORBIDDEN           => ['Your bot or user does not have permission for this action.', 'Check the required roles/permissions for this endpoint.'],
        self::NOT_FOUND           => ['The requested resource does not exist.', 'Check that the chat_id or message_id is correct.'],
        self::MISSING_FIELD       => ['A required parameter is missing from your request.', 'Check the API reference for required fields.'],
        self::INVALID_FIELD       => ['A parameter has the wrong type or format.', 'Check the API reference for parameter types.'],
        self::CONFLICT            => ['The resource already exists.', 'Delete the existing resource before creating a new one.'],
        self::METHOD_NOT_ALLOWED  => ['Wrong HTTP method for this endpoint.', 'This is likely an SDK bug — please open an issue.'],
        self::INVALID_PATH        => ['The API path does not exist.', 'Check the API reference or update the SDK.'],
        self::INTERNAL_ERROR      => ['Unexpected server-side error.', 'Retry after a short delay. If this persists, contact support.'],
        self::SERVICE_UNAVAILABLE => ['Service temporarily unavailable.', 'Retry with exponential backoff.'],
        self::UPSTREAM_ERROR      => ['An upstream dependency failed.', 'Retry after a short delay.'],
    ];

    public function __construct(
        public readonly string  $errorMessage,
        /** KappelaError::NOT_FOUND, FORBIDDEN, etc. */
        public readonly string  $errorCode,
        public readonly int     $status,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($this->buildMessage(), $status);
    }

    public static function fromArray(array $body, int $status, ?string $requestId = null): self
    {
        return new self(
            errorMessage: $body['message'] ?? 'Unknown error',
            errorCode:    $body['code']    ?? self::INTERNAL_ERROR,
            status:       $status,
            requestId:    $requestId,
        );
    }

    private function buildMessage(): string
    {
        $hint  = self::$hints[$this->errorCode] ?? ['Unknown error code.', ''];
        $lines = [
            "KappelaError [{$this->errorCode}] HTTP {$this->status}",
            "  Message  : {$this->errorMessage}",
            "  Why      : {$hint[0]}",
            "  Fix      : {$hint[1]}",
        ];
        if ($this->requestId !== null) {
            $lines[] = "  RequestID: {$this->requestId}  (quote this when contacting support)";
        }
        return implode("\n", $lines);
    }
}
