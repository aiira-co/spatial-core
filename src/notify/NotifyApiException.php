<?php

declare(strict_types=1);

namespace Spatial\Notify;

use RuntimeException;

final class NotifyApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
