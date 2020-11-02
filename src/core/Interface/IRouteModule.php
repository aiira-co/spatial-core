<?php

namespace Spatial\Core\Interface;

/**
 * Interface IRouteModule
 * @package Spatial\Interface
 */
interface IRouteModule
{
    public function render(?string $uri = null): void;
}
