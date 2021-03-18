<?php

namespace Spatial\Core\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interfaces IRouteModule
 * @package Spatial\Interfaces
 */
interface IRouteModule
{
    public function getControllerMethod(array $route, object $defaults, RequestInterface $request): ResponseInterface;

    public function controllerNotFound(string $body, int $statusCode): ResponseInterface;
}
