<?php

declare(strict_types=1);

namespace Spatial\Router;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use ReflectionParameter;
use Spatial\Core\Attributes\Injectable;
use Spatial\Core\Interfaces\RouteModuleInterface;
use Spatial\Router\Interfaces\IsAuthorizeInterface;
use Spatial\Router\Trait\SecurityTrait;

class RouterModule implements RouteModuleInterface
{
    use SecurityTrait;

    private ActiveRouteBuilder $_routes;
    private RouteBuilder $_routeMap;
    private string $_contentType = 'application/json';
    private object $defaults;

    private array $diServices = [];

    private ServerRequestInterface $request;
    private Container $container;


    public function setContainer(Container $diContainer): void
    {
        $this->container = $diContainer;
    }

    /**
     * @param string $body
     * @param int $statusCode
     * @return ResponseInterface
     * @throws JsonException
     */
    public function controllerNotFound(string $body, int $statusCode): ResponseInterface
    {
        $payload = json_encode(['message' => $body, 'status' => $statusCode], JSON_THROW_ON_ERROR);

        $response = new \GuzzleHttp\Psr7\Response();
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', $this->_contentType);
    }

    /**
     * @param array $route
     * @param object $defaults
     * @return ResponseInterface
     * @throws ReflectionException
     * @throws \Exception
     */
    public function getControllerMethod(
        array $route,
        object $defaults,
        ServerRequestInterface $request
    ): ResponseInterface {
        $this->defaults = $defaults;
        $this->request = $request;

        //        set activated route
//        $_REQUEST = $request->get;
//        print_r($request->getUri()->getQuery());
//        ActivatedRoute::setParams($request->getQueryParams());

        //                check for authguard
        if (
            $route['authGuard'] &&
            !$this->isAuthorized(... $this->getAuthGuardInstance($route['authGuard']))
        ) {
            return $this->controllerNotFound('Unauthorized', 401);
        }
        //     check constructor for DI later

        $args = [];
        foreach ($route['params'] as $param) {
            $value = $this->getBindSourceValue($param['bindingSource'], $param['param']);
            //$param is an instance of ReflectionParameter
            if ($value === null && !$param['param']->isOptional()) {
                throw new \Exception(
                    'Argument $' . $param['param']->getName(
                    ) . ' in ' . $route['controller'] . '->' . $route['action'] . '() is required'
                );
//                return $this->controllerNotFound('Controller Action Argument $' . $param['param'] . ' required', 500);
            }
            // echo $args;
            $args[] = $value;
        }
//        var_dump($args);
        try {
            $controller = ($this->container->get($route['controller']));
            $controller($request); // __invoke
            $response = $controller->{$route['action']}(...$args);
//            $this->setHeaders($response->getHeaders());

        } catch (DependencyException $e) {
            throw new DependencyException('Controller DI Error ' . $e->getMessage());
        } catch (NotFoundException $e) {
            throw new NotFoundException ('Controller ' . $route['controller'] . 'Not Found ' . $e->getMessage());
        }

//            set default content type
//        if (!isset($response->getHeaders()['Content-Type'])) {
//            $response->withHeader('Content-Type', $this->_contentType);

//        print_r($response->getHeaders());
//        }


        return $response->hasHeader('Content-Type') ? $response :
            $response->withHeader(
                'Content-Type',
                $this->_contentType
            );
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
            'FromBody' => (string)$this->request->getBody(),
            'FromForm' => $this->request->getUploadedFiles()[$paramName->getName()],
            'FromHeader' => $this->request->hasHeader($paramName->getName()) ? $this->request->getHeaderLine(
                $paramName->getName()
            ) : null,
            'FromQuery' => $this->request->getQueryParams()[$paramName->getName()],
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


        if (isset($this->diServices['$serviceName'])) {
            return $this->diServices['$serviceName'];
        }

        if (!class_exists($this->diServices['$serviceName'])) {
            return null;
        }

        $this->diServices['$serviceName'] = new $serviceName;

        return $this->diServices['$serviceName'];
    }

    /**
     * @param $serviceName
     * @return object|null
     */
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
    function setHeaders(
        array $headers
    ): void {
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = [$this->_contentType];
        }

        foreach ($headers as $header => $values) {
            foreach ($values as $v) {
                header($header . ':' . $v);
            }
        }
    }

    /**
     * @param $authorization
     * @return array
     * @throws DependencyException|NotFoundException
     */
    private function getAuthGuardInstance($authorization): array
    {
        $routeAuthGuards = [];
        foreach ($authorization as $authGaurd) {
            if (!isset($this->diServices[$authGaurd])) {
                try {
                    $this->diServices[$authGaurd] = $this->container->get($authGaurd);
                } catch (DependencyException $e) {
                    throw new DependencyException('Service DI Error' . $e->getMessage());
                } catch (NotFoundException $e) {
                    throw new NotFoundException('Service ' . $authGaurd . ' Error Not Found' . $e->getMessage());
                }
            }
            $routeAuthGuards[] = $this->diServices[$authGaurd];
        }
        return $routeAuthGuards;
    }

    private function isAuthorized(IsAuthorizeInterface ...$auhguard): bool
    {
        $allow = true;
        foreach ($auhguard as $auth) {
            if (!$auth->isAuthorized($this->request)) {
                $allow = false;
                break;
            }
        }
        return $allow;
    }
}
