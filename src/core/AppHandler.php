<?php


namespace Spatial\Core;


use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spatial\Core\Interfaces\IRouteModule;
use Spatial\Infrastructure\Storage;
use Spatial\Router\RouterModule;

class AppHandler implements RequestHandlerInterface
{
    private string $requestedMethod;
    private string $uri;
    private array $patternArray;
    private object $defaults;
    private object $routerModule;
    private array $routeActivated;
    private array $routeTable;

    public function __construct()
    {
        $this->routerModule = new RouterModule();
        $this->defaults = new class {
        };
    }

    public function setRouteTable(array $routeTable): void
    {
//        var_dump($routeTable);
        $this->routeTable = $routeTable;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->requestedMethod = strtolower($request->getMethod());
        $this->formatRoute($request->getUri()->getPath());

//        store to a static file
//        $GLOBALS['spatialUri'] = $this->uri;
//        $GLOBALS['spatialRequestedMethod'] = $this->requestedMethod;


        print_r('another request sent');
//        print_r(json_encode($this->routeTable));

        $routeFound = false;
//        $routeActivated;
        foreach ($this->routeTable as $route) {
//            echo '<br> is ' . $this->uri . ' === ' . $route['route'];
            $routeArr = explode('/', trim($route['route'], '/'));
            $routeHttp = $route['httpMethod'];
            if (
                str_contains($routeHttp, $this->requestedMethod) ||
                str_contains($routeHttp, 'all')
            ) {
                if ($this->isUriRoute($routeArr)) {
                    $routeFound = true;
                    $this->routeActivated = $route;
                    break;
                }
            }
        }


        return $routeFound ?
            $this->routerModule->getControllerMethod($this->routeActivated, $this->defaults) :

            $this->routerModule->controllerNotFound(
                'No Controller was routed to the uri ' . $this->uri . ' on a ' . $this->requestedMethod . ' method',
                404
            );
    }


    /**
     * @param $uri
     * @return string
     */
    #[Pure]
    private function formatRoute(
        string $uri
    ) {
        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        if ($uri === '') {
            return '/';
        }
        $this->uri = $uri;
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

        if ($routeArrCount < $this->patternArray['count']) {
            return false;
        }


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

//            echo '<br/> placeholder for token is --> ' . $placeholder;

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