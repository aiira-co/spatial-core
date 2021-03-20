<?php


namespace Spatial\Api\Services;


use Spatial\Router\Interfaces\IsAuthorizeInterface;

class AuthUser implements IsAuthorizeInterface
{

    public function canActivate(string $url): bool
    {
        return true;
    }
}