<?php


namespace Spatial\Core;

use Spatial\Core\Interfaces\ApplicationBuilderInterface;
use Spatial\Router\Interfaces\RouteBuilderInterface;
use Spatial\Router\RouteBuilder;

/**
 * Class ApplicationBuilder
 * @package Spatial\Core
 */
class ApplicationBuilder implements ApplicationBuilderInterface
{

    public bool $isSwooleWebsocket = false;
    public bool $isSwooleHttp = false;
    private RouteBuilderInterface $routeBuilder;
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