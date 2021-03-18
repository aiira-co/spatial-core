<?php

namespace Spatial\Core\Interfaces;

use Psr\Http\Message\ResponseInterface;

/**
 * Interfaces IRouteModule
 * @package Spatial\Interfaces
 */
interface IRouteModule
{
    public function getControllerMethod(array $route, object $defaults): ResponseInterface;

    public function controllerNotFound(string $body, int $statusCode): ResponseInterface;
}
