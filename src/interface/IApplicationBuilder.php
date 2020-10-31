<?php

declare(strict_types=1);

namespace Spatial\Interface;

/**
 * Interface IApplicationBuilder
 * @package Spatial\Interface
 */
interface IApplicationBuilder
{
    public function useSwooleHttp(): void;

    public function UseMvcWithDefaultRoute(): void;

    public function useMvc(callable $configureRoute): void;
}