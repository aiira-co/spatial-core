<?php

namespace Spatial\Router\Interfaces;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Interfaces to AuthGuard-ing
 */
interface IsAuthorizeInterface
{
    /**
     * This method is a must to authenticate
     *
     * @param ServerRequestInterface $request
     * @return boolean
     */
    public function isAuthorized(ServerRequestInterface $request): bool;
}
