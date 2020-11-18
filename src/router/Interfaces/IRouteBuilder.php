<?php

declare(strict_types=1);

namespace Spatial\Router\Interfaces;

interface IRouteBuilder
{
    public function mapDefaultControllerRoute();

    public function mapControllers();

    public function mapControllerRoute(string $name, string $pattern, ?object $defaults = null);
}