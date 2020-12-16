<?php


namespace Spatial\Api\Services;


use Spatial\Router\Interfaces\CanActivate;

class AuthIP implements CanActivate
{

    public function canActivate(string $url): bool
    {
        return true;
        // TODO: Implement canActivate() method.
    }
}