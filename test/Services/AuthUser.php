<?php


namespace Spatial\Api\Services;


use Spatial\Router\Interfaces\CanActivate;

class AuthUser implements CanActivate
{

    public function canActivate(string $url): bool
    {
        return true;
    }
}