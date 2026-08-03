<?php

declare(strict_types=1);

namespace Spatial\Core\DI;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;

/**
 * PHP-DI container that honours #[Injectable('request')] (and 'any').
 *
 * Root/platform providers stay worker singletons via parent::get().
 * Request-scoped providers are created with make() and cached only for the
 * current request (beginRequest / endRequest), which is required under Swoole.
 */
final class ScopedContainer extends Container
{
    /** @var array<string, true> */
    private array $requestScoped = [];

    /** @var array<string, mixed> */
    private array $requestInstances = [];

    private bool $inRequest = false;

    public function markRequestScoped(string $id): void
    {
        $this->requestScoped[$id] = true;
    }

    public function isRequestScoped(string $id): bool
    {
        return isset($this->requestScoped[$id]);
    }

    public function beginRequest(): void
    {
        $this->inRequest = true;
        $this->requestInstances = [];
    }

    public function endRequest(): void
    {
        $this->requestInstances = [];
        $this->inRequest = false;
    }

    public function get(string $id): mixed
    {
        if (! isset($this->requestScoped[$id])) {
            return parent::get($id);
        }

        if (! $this->inRequest) {
            // Boot / CLI: do not cache on the singleton map.
            return $this->make($id);
        }

        return $this->requestInstances[$id] ??= $this->make($id);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return parent::make($name, $parameters);
    }
}
