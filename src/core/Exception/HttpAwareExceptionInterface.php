<?php

declare(strict_types=1);

namespace Spatial\Core\Exception;

/**
 * Implemented by exceptions that already know which HTTP status they should
 * become, so ErrorHandlingMiddleware can translate them without a growing
 * instanceof chain and without depending on the packages that throw them.
 */
interface HttpAwareExceptionInterface
{
    public function getStatusCode(): int;

    /**
     * Short, client-safe reason. Must never contain infrastructure detail such
     * as host names, credentials or pool internals.
     */
    public function getErrorTitle(): string;

    /**
     * Seconds the client should wait before retrying, for statuses where that
     * is meaningful (429, 503). Null omits the Retry-After header.
     */
    public function getRetryAfter(): ?int;
}
