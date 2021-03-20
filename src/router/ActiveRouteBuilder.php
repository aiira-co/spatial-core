<?php

declare(strict_types=1);

namespace Spatial\Router;


class ActiveRouteBuilder extends RouteBuilderInterface
{
    private array $_routeMaps = [];

    /**
     * @param RouteBuilderInterface ...$routeMap
     */
    public function setHttpRoutes(RouteBuilderInterface ...$routeMap): void
    {
        $this->_routeMaps = $routeMap;
    }

    public function getMaps(): array
    {
        return $this->_routeMaps;
    }

}