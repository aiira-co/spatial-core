<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Injectable
 * @package Spatial\Core\Attributes
 * Injectable Attribute for DI Service
 *
 * providedIn:
 * - 'root' / 'platform' (default): one instance per worker (PHP-DI singleton)
 * - 'request' / 'any': one instance per HTTP request (cleared after the response)
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{

    /**
     * Determines which injectors will provide the injectable.
     *
     * - 'root' : The application-level injector (worker singleton under Swoole).
     * - 'platform' : Treated like root in this runtime.
     * - 'request' / 'any' : Unique instance per request; not eagerly created at boot.
     * - null : Not provided automatically; must appear in a module providers array.
     */
    public function __construct(public mixed $providedIn = 'root')
    {
    }


}