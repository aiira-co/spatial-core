<?php

namespace Spatial\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

readonly class RouterRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RouterModule $routerModule,
        private array        $routeActivated,
        private object       $defaults
    ) {}

    /**
     * @throws \ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->routerModule->getControllerMethod(
            $this->routeActivated,
            $this->defaults,
            $request
        );
    }
}