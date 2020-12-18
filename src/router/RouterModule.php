<?php

declare(strict_types=1);

namespace Spatial\Router;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;
use ReflectionParameter;
use Spatial\Core\Attributes\Injectable;
use Spatial\Core\Interfaces\IRouteModule;
use Spatial\Psr7\Response;
use Spatial\Router\Interfaces\CanActivate;
use Spatial\Router\Trait\SecurityTrait;

class RouterModule implements IRouteModule
{
    use SecurityTrait;

    private ActiveRouteBuilder $_routes;
    private RouteBuilder $_routeMap;
    private string $_contentType = 'application/json';
    private object $defaults;

    private array $diServices = [];
    private array $authGuards = [];


    public function __construct(private Container $container)
    {
    }

    private function isAuthorized(CanActivate ...$auhguard): bool
    {
        $allow = true;
        foreach ($auhguard as $auth) {
            if (!$auth->canActivate($_SERVER['REQUEST_URI'])) {
                $allow = false;
                break;
            }
        }
        return $allow;
    }

    /**
     * @param array $route
     * @param object $defaults
     * @throws ReflectionException
     */
    public function render(array $route, object $defaults): void
    {
//        check first fir authorization

        if (
            $route['canActivate'] &&
            !$this->isAuthorized(... $this->getAuthGuardInstance($route['canActivate']))
        ) {
            http_response_code(401);

            return;
        }
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

    /**
     * @param array $route
     * @param object $defaults
     * @return Response
     * @throws ReflectionException
     */
    private function getControllerMethod(array $route, object $defaults): Response
    {
        $this->defaults = $defaults;
        //                check for authguard
        //     check constructor for DI later

        $args = [];
        foreach ($route['params'] as $param) {
            $value = $this->getBindSourceValue($param['bindingSource'], $param['param']);
            //$param is an instance of ReflectionParameter
            if ($value === null && !$param['param']->allowsNull()) {
                die(
                    'Argument $' . $param['param']->getName(
                    ) . ' in ' . $route['controller'] . '->' . $route['action'] . '() is required'
                );
            }
            // echo $args;
            $args[] = $value;
        }
//        var_dump($args);
        try {
            return ($this->container->get($route['controller']))->{$route['action']}(...$args);
        } catch (DependencyException $e) {
            die ('Controller DI Error ');
        } catch (NotFoundException) {
            die ('Controller ' . $route['controller'] . 'Not Found ');
        }
    }

    /**
     * @param string $bindingSource
     * @param ReflectionParameter $paramName
     * @return mixed
     * @throws ReflectionException
     */
    private
    function getBindSourceValue(
        string $bindingSource,
        ReflectionParameter $paramName
    ): mixed {
        return match ($bindingSource) {
            'FromBody' => file_get_contents('php://input'),
            'FromForm' => $_FILES[$paramName->getName()] ?? null,
            'FromHeader' => $_SERVER[$paramName->getName()] ?? null,
            'FromQuery' => $_GET[$paramName->getName()] ?? null,
            'FromRoute' => $this->defaults->{$paramName->getName()} ?? null,
            'FromServices' => $this->getServiceFromProvider($paramName),
            default => $this->defaults->{$paramName->getName()} ?? $paramName->getDefaultValue() ?? null
        };
    }

    /**
     * @param ReflectionParameter $parameter
     * @return object|null
     */
    private
    function getServiceFromProvider(
        ReflectionParameter $parameter
    ): ?object {
        $serviceName = $parameter->getType();

        try {
            return $this->container->get($serviceName);
        } catch (DependencyException $e) {
            die('Service DI Error');
        } catch (NotFoundException $e) {
            die('Service ' . $serviceName . ' Error');
        }


//        if (isset($this->diServices['$serviceName'])) {
//            return $this->diServices['$serviceName'];
//        }
//
//        if (!class_exists($this->diServices['$serviceName'])) {
//            return null;
//        }
//
//        $this->diServices['$serviceName'] = new $serviceName;
//
//        return $this->diServices['$serviceName'];
    }


    private
    function instantiateService(
        $serviceName
    ): ?object {
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
    private
    function _setHeaders(
        array $headers
    ): void {
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

    /**
     * @param $authorization
     * @return array
     */
    private function getAuthGuardInstance($authorization): array
    {
        $routeAuthGuards = [];
        foreach ($authorization as $authGaurd) {
            if (!isset($this->diServices[$authGaurd])) {
                try {
                    $this->diServices[$authGaurd] = $this->container->get($authGaurd);
                } catch (DependencyException $e) {
                    die('Service DI Error' . $e->getMessage());
                } catch (NotFoundException $e) {
                    die('Service ' . $authGaurd . ' Error Not Found' . $e->getMessage());
                }
            }
            $routeAuthGuards[] = $this->diServices[$authGaurd];
        }
        return $routeAuthGuards;
    }
}
