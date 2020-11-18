<?php


namespace Spatial\Core;

use Spatial\Core\Interfaces\IApplicationBuilder;
use Spatial\Router\Interfaces\IRouteBuilder;
use Spatial\Router\RouteBuilder;

/**
 * Class ApplicationBuilder
 * @package Spatial\Core
 */
class ApplicationBuilder implements IApplicationBuilder
{

    public bool $isSwooleWebsocket = false;
    public bool $isSwooleHttp = false;
    private IRouteBuilder $routeBuilder;
    public array $routingType = [];

    public function __construct()
    {
        $this->routeBuilder = new RouteBuilder();
    }

    public function useSwooleHttp(): void
    {
        $this->isSwooleHttp = true;
    }

    public function useDeveloperExceptionPage(): void
    {
        // TODO: Implement useDeveloperExceptionPage() method.
    }

    public function useHttpsRedirection(): void
    {
        // TODO: Implement useHttpsRedirection() method.
    }

    public function useRouting(): void
    {
        // TODO: Implement useRouting() method.
    }

    public function useAuthorization(): void
    {
        // TODO: Implement useAuthorization() method.
    }

    public function useEndpoints(callable $endpoint): void
    {
        $this->routingType = $endpoint($this->routeBuilder);
        // TODO: Implement useEndpoints() method.
    }
}