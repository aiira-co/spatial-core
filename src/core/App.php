<?php


namespace Spatial\Core;


use Exception;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionException;
use Spatial\Core\Attributes\ApiModule;
use Spatial\Core\Interface\IApplicationBuilder;
use Spatial\Core\Interface\IRouteModule;

/**
 * Class App
 * @package Spatial\Core
 */
class App
{
    private IApplicationBuilder $applicationBuilder;
    /**
     * @var array|string[]
     */
    private array $httpVerbs = [
        'HttpGet',
        'HttpPost',
        'HttpPut',
        'HttpDelete',
        'HttpHead',
        'HttpPatch',
    ];

    /**
     * For Conventional routing -> pattern: "{controller=Home}/{action=Index}/{id?}"
     * For Attributes -> [Route("[controller]/[action]")]
     * @var array|string[]
     */
    private array $reservedRoutingNames = [
        'action',
        'area',
        'controller',
        'handler',
        'page'
    ];

    /**
     * Keep Track of Imported Modules,
     * Inherit their exports to one $declarations
     * module declarations that are not exported are kept in the module
     * scope for routing and functional use.
     * Providers hold @Injectables for module declarations
     * @var array
     */
    private array $importModules = [];


    /**
     * Includes declarations from the appModules
     * and exports from imported modules
     * @var array
     */
    private array $providers = [];

    /**
     * Modules declarations with imported declarations from import module's exports
     * @var array
     */
    private array $declarations = [];


    private array $patternArray;
    private object $defaults;

    public array $status = ['code' => 401, 'reason' => 'Unauthorized'];


    #[Pure]
    public function __construct(
        ?string $uri = null
    ) {
        $uri = $this->_formatRoute($uri ?? $_SERVER['REQUEST_URI']);

        $this->patternArray['uri'] = explode('/', trim(urldecode($uri), '/'));
        $this->patternArray['count'] = count($this->patternArray['uri']);

        $this->applicationBuilder = new ApplicationBuilder();
    }

    /**
     * @param $uri
     * @return string
     */
    #[Pure]
    private function _formatRoute(
        $uri
    ): string {
        // Strip query string (?foo=bar) and decode URI
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        if ($uri === '') {
            return '/';
        }
        return $uri;
    }


    /**
     * @param object|string $appModule
     * @return App|null expects all params to have an attribute
     * expects all params to have an attribute
     * @throws ReflectionException
     */
    public function bootstrapModule(string $appModule): ?self
    {
        $reflectionClass = new ReflectionClass($appModule);
        $reflectionClassApiAttributes = $reflectionClass->getAttributes(ApiModule::class);

        if (count($reflectionClassApiAttributes) === 0) {
            return $this;
        }

//        print_r($reflectionClassApiAttributes[0]->newInstance());
        $apiModuleAttributes = $reflectionClassApiAttributes[0]->newInstance();

//        $apiModule = $apiModuleAttributes[0];

//                load attribute metadata
        $this->resolveAppModule($apiModuleAttributes);

//          load configs
        $baseModule = $reflectionClass->newInstance();
        $baseModule->configure($this->applicationBuilder);

        return null;
    }


    /**
     * Make sure to avoid circle imports
     * @param ApiModule $app
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    private function resolveAppModule(ApiModule $app): bool
    {
//        find the import with routeModule
        $routeModule = $this->getParams($app->imports);
        if ($routeModule === null) {
            return false;
        }

        $this->resolveProviders($app->providers);

        $routeModule->render();
        return true;
    }

    /**
     * @param array $moduleProviders
     * @throws ReflectionException
     */
    private function resolveProviders(array $moduleProviders): void
    {
        foreach ($moduleProviders as $provider) {
            if (!isset($this->providers[$provider])) {
                //            check if it has DI for provider
                $this->providers[$provider] = new ReflectionClass($provider);
            }
        }
    }

    /**
     * @param array $moduleParam
     * @param string|null $classInstance
     * @return object|null
     * @throws Exception
     */
    private function getParams(array $moduleParam, ?string $classInstance = null): ?object
    {
        foreach ($moduleParam as $param) {
            if (isset($this->importModules[$param])) {
                throw new \RuntimeException('Import Module ' . $param . ' is already imported in module');
            }
//            check if it has DI for provider
            $this->importModules[$param] = new ReflectionClass($param);
        }
        return null;
    }

    public function catch(callable $exceptionCallable): void
    {
        $exceptionCallable();
    }

    /**
     * @param array $uriArr
     * @param string $token
     * @return bool
     */
    public function isUriRoute(array $uriArr, string $token = '{}'): bool
    {
        $isMatch = true;
        $isToken = false;

        for ($i = 0; $i < $this->patternArray['count']; $i++) {
            if (str_starts_with($this->patternArray['uri'][$i][0], $token[0])) {
                $isToken = true;

                if (!isset($uriArr[$i]) || !($this->patternArray['uri'][$i] === $uriArr[$i])) {
                    $isMatch = false;
                    $isToken = false;
                    break;
                }
            }


            if ($isToken) {
                $placeholder = str_replace(
                    [$token[0], $token[1]],
                    '',
                    $this->patternArray['uri'][$i]
                );
//                verify token for only [], {} can be used for everything
                if ($token === '[]' && !in_array($placeholder, $this->reservedRoutingNames, true)) {
                    $this->status['reason'] = $placeholder . ' is not a reserved routing token';
                    break;
                }
            } else {
                $placeholder = $this->patternArray['uri'][$i];
            }

            // check to see if its the last placeholder
            // AND if the placeholder is prefixed with `...`
            // meaning the placeholder is an array of the rest of the uriArr member
            if ($i === ($this->patternArray['count'] - 1) && str_starts_with($placeholder, '...')) {
                $placeholder = ltrim($placeholder, '/././.');
                if (isset($uriArr[$i])) {
                    for ($uri = $i, $uriMax = count($uriArr); $uri < $uriMax; $uri++) {
                        $this->replaceRouteToken($placeholder, $uriArr[$uri], true);
                    }
                }
                break;
            }
            $this->replaceRouteToken($placeholder, $uriArr[$i] ?? null);
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


    /**
     * @param string $controllerClass
     * @return array
     * @throws ReflectionException
     */
    private function resolveController(string $controllerClass): array
    {
        $reflectionClass = new ReflectionClass($controllerClass);

        $listeners = [];

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes();

            foreach ($attributes as $attribute) {
                $listener = $attribute->newInstance();

                $listeners[] = [
                    // The event that's configured on the attribute
                    $listener->event,

                    // The listener for this event
                    [$controllerClass, $method->getName()],
                ];
            }
        }

        return $listeners;
    }

    /**
     * @return string
     */
    private function _getRequestedMethod(): string
    {
//        $method = 'httpGet';


        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $httpRequest = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'];
        } else {
            $httpRequest = $_SERVER['REQUEST_METHOD'];
        }


        return match ($httpRequest) {
            'GET' => 'httpGet',
            'POST' => 'httpPost',
            'PUT' => 'httpPut',
            'DELETE' => 'httpDelete',
            default => 'http' . ucfirst(strtolower($httpRequest)),
        };
    }
}