<?php

namespace Spatial\Core\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interfaces IRouteModule
 * @package Spatial\Interfaces
 */
interface RouteModuleInterface
{
    public function getControllerMethod(
        array $route,
        object $defaults,
        ServerRequestInterface $request
    ): ResponseInterface;

    public function controllerNotFound(
        string $body,
        int $statusCode,
        ServerRequestInterface $request
    ): ResponseInterface;
}
