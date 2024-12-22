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
use Spatial\Common\Helper\Caster;
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
    public function quickResponse(
        string $body,
        int $statusCode,
        ServerRequestInterface $request
    ): ResponseInterface {
        $this->request = $request;


//        handling options
        if (strtolower($request->getMethod()) === 'options') {
            $statusCode = 200;
            $body = 'Accept Request';
        }

        $payload = json_encode(['status' => $statusCode, 'success' => $statusCode === 200,'message' => $body], JSON_THROW_ON_ERROR);

        $response = new \GuzzleHttp\Psr7\Response($statusCode);
        $response->getBody()->write($payload);
        $response = $this->setCors($response);

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
            return $this->quickResponse('Unauthorized', 401, $this->request);
        }
        //     check constructor for DI later
        try {
            $args = [];
            foreach ($route['params'] as $param) {
                $value = $this->getBindSourceValue($param['bindingSource'], $param['param']);
                //$param is an instance of ReflectionParameter
                if ($value === null && !$param['param']->isOptional()) {
                    throw new \Exception(
                        'Argument $' . $param['param']->getName(
                        ) . ' in ' . $route['controller'] . '->' . $route['action'] . '() is required'
                    );
                }
                $args[] = $value;
            }


            $controller = ($this->container->get($route['controller']));
            $controller($request); // __invoke
            $controllerResponse = $controller->{$route['action']}(...$args);
            $response = $this->setCors($controllerResponse);

            return $response->hasHeader('Content-Type') ? $response :
                $response->withHeader(
                    'Content-Type',
                    $this->_contentType
                );

        } catch (DependencyException $e) {
            throw new DependencyException('Controller DI Error ' . $e->getMessage());
        } catch (NotFoundException $e) {
            throw new NotFoundException ('Controller ' . $route['controller'] . 'Not Found ' . $e->getMessage());
        } catch (\TypeError $e) {
            throw new \Exception('Response Type Error ' . json_encode($controllerResponse) . ' ' . $e->getMessage());
        }catch(\Exception $e) {
            return $this->quickResponse($e->getMessage(), 500, $this->request);
        }


    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function setCors(ResponseInterface $response): ResponseInterface
    {
        if ($this->request->hasHeader('origin')) {
            $origin = $this->request->getHeader('origin')[0];
        } elseif ($this->request->hasHeader('referer')) {
            $origin = $this->request->getHeader('referer')[0];
        } else {
            $origin = $_SERVER['REMOTE_ADDR'] ?? '*';
        }
        $parsedOrigin = [];

        if ($origin !== '*') {
            $parsedOrigin = parse_url($origin);
            $origin = $parsedOrigin['scheme'] . '://' . $parsedOrigin['host'];
        }


        if (isset($parsedOrigin['port']) && $parsedOrigin['port'] !== null) {
            $origin .= ':' . $parsedOrigin['port'];
        }

        $res = $response;
//        print_r('origin is ' . $origin);
//
//        print_r('request method is ' . $this->request->getMethod());
//        Check If Origin is Allowed
        if (in_array($origin, AppConfig['header']['allowed_domains'], true)) {
//            print_r('allow origin');
//            header('Access-Control-Allow-Origin: ' . $origin);
            $res = $response->withHeader('Access-Control-Allow-Origin', $origin);
        }

        return $res
            ->withAddedHeader('Access-Control-Allow-Headers', AppConfig['header']['allowed_headers'])
            ->withHeader('Access-Control-Allow-Methods', AppConfig['header']['allowed_methods'])
            ->withHeader(
                'Access-Control-Allow-Credentials',
                AppConfig['header']['allow_credentials']
            ) // link to framework settings
            ->withHeader(
                'Access-Control-Max-Age',
                AppConfig['header']['max_cache_age']
            ); // link to framework settings;
    }

    /**
     * Retrieves the value for a parameter based on its binding source.
     *
     * @param string $bindingSource
     * @param ReflectionParameter $paramName
     * @return mixed
     */
    private function getBindSourceValue(
        string $bindingSource,
        ReflectionParameter $paramName
    ): mixed {
        return match ($bindingSource) {
            'FromBody' => $this->getFromBody($paramName),
            'FromForm' => $this->getFromForm($paramName),
            'FromHeader' => $this->getFromHeader($paramName),
            'FromQuery' => $this->getFromQuery($paramName),
            'FromRoute' => $this->getFromRoute($paramName),
            'FromServices' => $this->getFromServices($paramName),
            default => $this->getDefault($paramName)
        };
    }

    /**
     * Handles 'FromBody' binding.
     */
    private function getFromBody(ReflectionParameter $paramName): mixed
    {
        $body = (string)$this->request->getBody();
        return class_exists($paramName->getType()?->getName())
            ? Caster::castToObject($paramName->getType()->getName(), $body)
            : $body;
    }

    /**
     * Handles 'FromForm' binding.
     */
    private function getFromForm(ReflectionParameter $paramName): mixed
    {
        return $this->request->getUploadedFiles()[$paramName->getName()] ?? null;
    }

    /**
     * Handles 'FromHeader' binding.
     */
    private function getFromHeader(ReflectionParameter $paramName): mixed
    {
        return $this->request->hasHeader($paramName->getName())
            ? $this->request->getHeaderLine($paramName->getName())
            : null;
    }

    /**
     * Handles 'FromQuery' binding.
     */
    private function getFromQuery(ReflectionParameter $paramName): mixed
    {
        return $this->request->getQueryParams()[$paramName->getName()] ?? null;
    }

    /**
     * Handles 'FromRoute' binding.
     */
    private function getFromRoute(ReflectionParameter $paramName): mixed
    {
        return $this->defaults->{strtolower($paramName->getName())} ?? null;
    }

    /**
     * Handles 'FromServices' binding.
     */
    private function getFromServices(ReflectionParameter $paramName): mixed
    {
        return $this->getServiceFromProvider($paramName);
    }

    /**
     * Handles default case.
     */
    private function getDefault(ReflectionParameter $paramName): mixed
    {
        return $this->defaults->{strtolower($paramName->getName())}
            ?? ($paramName->isDefaultValueAvailable() ? $paramName->getDefaultValue() : null);
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
