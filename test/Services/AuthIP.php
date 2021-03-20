<?php


namespace Spatial\Api\Services;


use Spatial\Router\Interfaces\IsAuthorizeInterface;

class AuthIP implements IsAuthorizeInterface
{

    public function canActivate(string $url): bool
    {
        return true;
        // TODO: Implement canActivate() method.
    }
}