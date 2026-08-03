<?php

declare(strict_types=1);

namespace Spatial\Core\DI;

/**
 * Known #[Injectable] providedIn values.
 */
final class InjectableScope
{
    public const ROOT = 'root';
    public const PLATFORM = 'platform';
    public const REQUEST = 'request';

    /** Angular-style alias treated as request scope under Swoole. */
    public const ANY = 'any';

    public static function isRequestScoped(mixed $providedIn): bool
    {
        if ($providedIn === null) {
            return false;
        }

        $value = is_string($providedIn) ? strtolower(trim($providedIn)) : '';

        return $value === self::REQUEST || $value === self::ANY;
    }
}
