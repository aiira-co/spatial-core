<?php

namespace Spatial\Core\Interfaces;

/**
 * Interfaces IRouteModule
 * @package Spatial\Interfaces
 */
interface IRouteModule
{
    public function render(array $route, object $defaults): \Spatial\Psr7\Response;
}
