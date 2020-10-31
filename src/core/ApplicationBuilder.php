<?php


namespace Spatial\Core;

use Spatial\Interface\IApplicationBuilder;

/**
 * Class ApplicationBuilder
 * @package Spatial\Core
 */
class ApplicationBuilder implements IApplicationBuilder
{
    /**
     * Use Swoole HttpServer. Expose Swoole Setting/Take In
     */
    public function useSwooleHttp(): void
    {
        // TODO: Implement useSwooleHttp() method.
    }

    public function UseMvcWithDefaultRoute(): void
    {
        // TODO: Implement UseMvcWithDefaultRoute() method.
    }

    public function useMvc(callable $configureRoute): void
    {
        // TODO: Implement useMvc() method.
    }
}