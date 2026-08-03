<?php

declare(strict_types=1);

namespace Spatial\Telemetry;

/**
 * W3C traceparent helpers for non-HTTP carriers (integration events, AMQP).
 */
final class Traceparent
{
    private const PATTERN = '/^[0-9a-f]{2}-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/';

    public static function isValid(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim($value));

        return $normalized !== '' && preg_match(self::PATTERN, $normalized) === 1;
    }

    public static function normalize(?string $value): ?string
    {
        if (! self::isValid($value)) {
            return null;
        }

        return strtolower(trim((string) $value));
    }
}
