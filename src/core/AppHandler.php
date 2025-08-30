<?php


namespace Spatial\Core;


use DI\Container;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spatial\Router\RouterModule;
use Spatial\Router\RouterRequestHandler;

class AppHandler implements RequestHandlerInterface
{
    private string $uri;
    private array $patternArray;
    private object $defaults;
    private RouterModule $routerModule;
    private array $routeActivated;
    private array $routeTable;
    private Container $diContainer;

    public function __construct()
    {
        $this->routerModule = new RouterModule();
        $this->defaults = new \stdClass();
    }

    public function passParams(array $routeTable, Container $diContainer): void
    {
//        var_dump($routeTable);
        $this->routeTable = $routeTable;
        $this->routerModule->setContainer($diContainer);

    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \JsonException
     * @throws \ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $requestedMethod = strtolower($request->getMethod());
        $this->formatRoute($request->getUri());

//        print_r($request->getParsedBody() ?? file_get_contents('php://input'));


        $routeFound = false;
//        $routeActivated;
        foreach ($this->routeTable as $route) {
//            echo '<br> is ' . $this->uri . ' === ' . $route['route'];
            $routeArr = explode('/', trim($route['route'], '/'));
            $routeHttp = $route['httpMethod'];
            if (
                str_contains($routeHttp, $requestedMethod) ||
                str_contains($routeHttp, 'all')

            ) {
//                print_r('match found to check route');
                if ($this->isUriRoute($routeArr)) {
                    $routeFound = true;
                    $this->routeActivated = $route;
                    break;
                }
            }
        }

        if($routeFound){
            $module = $this->routeActivated['module'];
            if($module == 'root'){
                return $this->routerModule->getControllerMethod($this->routeActivated, $this->defaults, $request);
            }


//            $handlerFn = function () use ($this->routerModule, $this->routeActivated, $this->defaults) {
//                return new RouterRequestHandler(
//                    $this->routerModule,
//                    $this->routeActivated,
//                    $this->defaults
//                );
//            };

            echo '<br> module is ' . $module;

            return App::pipeMiddleware($module)
                ->process(
                    request: $request,
                    handler: new RouterRequestHandler(
                        $this->routerModule,
                        $this->routeActivated,
                        $this->defaults
                    )
                );

        }


        return   $this->routerModule->quickResponse(
            'No Controller was routed to the uri ' . $this->uri . ' on a ' . $requestedMethod . ' method',
            404,
            $request
        );

    }


    /**
     * @param UriInterface $requestUri
     * @return void
     */
    private function formatRoute(
        UriInterface $requestUri
    ): void
    {
        $host = $requestUri->getHost();
        $uri = $requestUri->getPath();

        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

//        print_r('host is ' . $host . ' --  uri path is ' . $uri);


//        strip host & domain from uri
        if (str_starts_with($uri, $host)) {
            $uri = ltrim($uri, $host);
        }

        $uri = rawurldecode($uri);


        $this->uri = $uri === '' ? '/' : $uri;
        $this->patternArray['uri'] = explode('/', trim(urldecode($this->uri), '/'));
        $this->patternArray['count'] = count($this->patternArray['uri']);
    }

    /**
     * @param array $routeUriArr
     * @param string $token
     * @return bool
     */
    private function isUriRoute(array $routeUriArr, string $token = '{}'): bool
    {
        $isMatch = true;
//        print_r($this->patternArray);
//        print_r($routeUriArr);
        $routeArrCount = count($routeUriArr);
//        print_r('routeCount -> ' . $routeArrCount . ' uri count is ->' . $this->patternArray['count']);

        if ($routeArrCount < $this->patternArray['count'] && !str_starts_with(
                $routeUriArr[$routeArrCount - 1],
                $token[0] . '...'
            )) {
            return false;
        }

//        print_r('started to match uri to route');


        for ($i = 0; $i < $routeArrCount; $i++) {
            $isToken = str_starts_with($routeUriArr[$i], $token[0]);
            if (!$isToken) {
                if (
                    !isset($this->patternArray['uri'][$i]) ||
                    !($this->patternArray['uri'][$i] === $routeUriArr[$i])
                ) {
                    $isMatch = false;
                    break;
                }
                continue;
            }


            $placeholder =
                str_replace(
                    [$token[0], $token[1]],
                    '',
                    $routeUriArr[$i]
                );

//            print_r('n\ placeholder for token is --> ' . $placeholder);

            // check to see if its the last placeholder
            // AND if the placeholder is prefixed with `...`
            // meaning the placeholder is an array of the rest of the uriArr member
            if ($i === ($routeArrCount - 1) && str_starts_with($placeholder, '...')) {
                $placeholder = ltrim($placeholder, '/././.');
                if (isset($this->patternArray['uri'][$i])) {
                    for ($uri = $i, $uriMax = count($this->patternArray['uri']); $uri < $uriMax; $uri++) {
                        $this->replaceRouteToken($placeholder, $this->patternArray['uri'][$uri], true);
                    }
                }
                break;
            }
            $this->replaceRouteToken($placeholder, $this->patternArray['uri'][$i] ?? null);
        }

        return $isMatch;
    }

    /**
     * @param string $placeholderString
     * @param string|null $uriValue
     * @param bool $isList
     */
    private function replaceRouteToken(string $placeholderString, ?string $uriValue, bool $isList = false): void
    {
        // separate constraints
        $placeholder = explode(':', $placeholderString);

        $value = $uriValue ?? $this->defaults->{$placeholder[0]} ?? null;

        if (isset($placeholder[1])) {
            $typeValue = explode('=', $placeholder[1]);
            if (isset($typeValue[1])) {
                $value = $value ?? $typeValue[1];
            }
            if ($value !== null) {
                $value = match ($placeholder[1]) {
                    'int' => (int)$value,
                    'bool' => (bool)$value,
                    'array' => (array)$value,
                    'float' => (float)$value,
                    'object' => (object)$value,
                    default => (string)$value,
                };
            }
        }
        // set value
        $isList ?
            $this->defaults->{$placeholder[0]}[] = $value :
            $this->defaults->{$placeholder[0]} = $value;
    }


}