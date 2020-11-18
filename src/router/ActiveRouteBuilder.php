<?php

declare(strict_types=1);

namespace Spatial\Router;


class ActiveRouteBuilder extends RouteBuilder
{
    private array $_routeMaps = [];

    /**
     * @param RouteBuilder ...$routeMap
     */
    public function setHttpRoutes(RouteBuilder ...$routeMap): void
    {
        $this->_routeMaps = $routeMap;
    }

    public function getMaps(): array
    {
        return $this->_routeMaps;
    }

}