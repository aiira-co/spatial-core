<?php

namespace Spatial\Interface;


interface IRouteModule
{
    public function render(?string $uri = null): void;
}
