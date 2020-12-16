<?php


namespace Spatial\Api;


use Spatial\Router\Interfaces\CanActivate;

class AuthUser implements CanActivate
{

    public function canActivate(string $url): bool
    {
        return true;
    }
}