<?php

declare(strict_types=1);

namespace Spatial\Router;

use Psr\Http\Message\ResponseInterface;
use ReflectionParameter;
use Spatial\Core\Attributes\Injectable;
use Spatial\Core\Interfaces\IRouteModule;
use Spatial\Psr7\Response;
use Spatial\Router\Trait\SecurityTrait;

class RouterModule implements IRouteModule
{
    use SecurityTrait;

    private ActiveRouteBuilder $_routes;
    private RouteBuilder $_routeMap;
    private string $_contentType = 'application/json';
    private object $defaults;

    private array $diServices = [];

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
        $this->defaults = $defaults;
        //                check for authguard
        //     check constructor for DI later

        $args = [];
        foreach ($route['params'] as $param) {
            $value = $this->getBindSourceValue($param['bindingSource'], $param['param']);
            //$param is an instance of ReflectionParameter
            if ($value === null && !$param['param']->isOptional()) {
                die('argument ' . $param->getName() . ' required');
            }
            // echo $args;
            $args[] = $value;
        }
//        var_dump($args);
        return (new $route['controller'])->{$route['action']}(...$args);
    }

    /**
     * @param string $bindingsource
     * @param ReflectionParameter $paramName
     * @return mixed
     */
    private function getBindSourceValue(string $bindingsource, ReflectionParameter $paramName): mixed
    {
        return match ($bindingsource) {
            'FromBody' => file_get_contents('php://input'),
            'FromForm' => $_FILES[$paramName->getName()] ?? null,
            'FromHeader' => $_SERVER[$paramName->getName()] ?? null,
            'FromQuery' => $_GET[$paramName->getName()] ?? null,
            'FromRoute' => $this->defaults->{$paramName->getName()} ?? null,
            'FromServices' => $this->getServiceFromProvider($paramName),
        };
    }

    /**
     * @param ReflectionParameter $parameter
     * @return object|null
     */
    private function getServiceFromProvider(ReflectionParameter $parameter): ?object
    {
        $serviceName = $parameter->getType();


        if (isset($this->diServices['$serviceName'])) {
            return $this->diServices['$serviceName'];
        }

        if (!class_exists($this->diServices['$serviceName'])) {
            return null;
        }

        $this->diServices['$serviceName'] = new $serviceName;

        return $this->diServices['$serviceName'];
    }


    private function instantiateService($serviceName): ?object
    {
        //        make sure it has injectable attribute
        $serviceReflection = new \ReflectionClass($serviceName);
        $serviceInjectableAttribute = $serviceReflection->getAttributes(Injectable::class);

        if (count($serviceInjectableAttribute) === 0) {
            return null;
        }

//        check  for dependencies too
        $constructorParams = $serviceReflection->getConstructor()?->getParameters();
        foreach ($constructorParams as $param) {
            if (!$param->isPromoted()) {
                continue;
            }
            if ($param->getType()->allowsNull()) {
                continue;
            };
        }

        return null;
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
