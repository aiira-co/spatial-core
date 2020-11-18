<?php

declare(strict_types=1);

namespace Spatial\Router;

use Psr\Http\Message\ResponseInterface;
use Spatial\Core\Interfaces\IRouteModule;
use Spatial\Psr7\Response;
use Spatial\Router\Trait\SecurityTrait;

class RouterModule implements IRouteModule
{
    use SecurityTrait;

    private ActiveRouteBuilder $_routes;
    private RouteBuilder $_routeMap;
    private string $_contentType='application/json';

    /**
     * @param array $route
     * @param object $defaults
     */
    public function render(array $route, object $defaults): void
    {
//        check first fir authorization
//        if (!$this->isAuthorized) {
//            http_response_code(401);
//            return;
//        }
//        $uri = $uri ?? $_SERVER['REQUEST_URI'];
        // echo $this->_resolve($uri)->getHeaderLine('Content-Type');
        $response = $this->getControllerMethod($route, $defaults);
        $this->_setHeaders($response->getHeaders());

        // $this->_contentType = $this->_resolve($uri)->getHeaderLine('Content-Type') ?? $this->_contentType;
        // var_dump($response->getHeaders());
        http_response_code($response->getStatusCode());
        echo $response->getBody();
        // echo $this->_resolve($uri)->getBody()->getContents();
    }

    private function getControllerMethod(array $route, object $defaults): Response
    {
        //                check for authguard
        //     check contructor for DI later

        $args = [];
        foreach ($route['params'] as $param) {
            //$param is an instance of ReflectionParameter
            if (!$param->isOptional() && !property_exists($defaults, $param->getName())) {
                die('argument ' . $param->getName() . ' required');
            }
            // echo $args;
            $args[] = $defaults->{$param->getName()};
        }
//        var_dump($args);
        return (new $route['controller'])->{$route['action']}(...$args);
    }

    /**
     * @param array $headers
     */
    private function _setHeaders(array $headers): void
    {
        // $headerKeys = array_keys($header);
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = [$this->_contentType];
        }

        // var_dump($headers);
        foreach ($headers as $header => $values) {
            foreach ($values as $v) {
                # code...
                header($header . ':' . $v);
            }
        }
    }
}
