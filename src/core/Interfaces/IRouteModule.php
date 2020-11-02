<?php

namespace Spatial\Core\Interfaces;

/**
 * Interfaces IRouteModule
 * @package Spatial\Interfaces
 */
interface IRouteModule
{
    public function render(?string $uri = null): void;
}
