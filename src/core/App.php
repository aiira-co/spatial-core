<?php


namespace Spatial\Core;


use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Spatial\Common\BindSourceAttributes\FromBody;
use Spatial\Common\BindSourceAttributes\FromForm;
use Spatial\Common\BindSourceAttributes\FromHeader;
use Spatial\Common\BindSourceAttributes\FromQuery;
use Spatial\Common\BindSourceAttributes\FromRoute;
use Spatial\Common\BindSourceAttributes\FromServices;
use Spatial\Common\HttpAttributes\HttpDelete;
use Spatial\Common\HttpAttributes\HttpGet;
use Spatial\Common\HttpAttributes\HttpHead;
use Spatial\Common\HttpAttributes\HttpPatch;
use Spatial\Common\HttpAttributes\HttpPost;
use Spatial\Common\HttpAttributes\HttpPut;
use Spatial\Core\Attributes\ApiController;
use Spatial\Core\Attributes\ApiModule;
use Spatial\Core\Attributes\Area;
use Spatial\Core\Attributes\Authorize;
use Spatial\Core\Attributes\Injectable;
use Spatial\Core\Attributes\Route;
use Spatial\Core\Interfaces\ApplicationBuilderInterface;
use Spatial\Core\Interfaces\RouteModuleInterface;
use Spatial\Router\RouterModuleInterface;
use Spatial\Telemetry\OtelProviderFactory;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use function Psl\Str\uppercase;
use Psr\Log\LoggerInterface;
/**
 * Class App
 * @package Spatial\Core
 */
class App implements MiddlewareInterface
{
    private ApplicationBuilderInterface $applicationBuilder;
    private RouteModuleInterface $routerModule;
    private LoggerInterface $logger;

    /**
     * @var array|string[]
     */
    private array $httpVerbs = [
        'HttpGet' => HttpGet::class,
        'HttpPost' => HttpPost::class,
        'HttpPut' => HttpPut::class,
        'HttpDelete' => HttpDelete::class,
        'HttpHead' => HttpHead::class,
        'HttpPatch' => HttpPatch::class,
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

    private array $reservedBindingSourceAttributes = [
        'FromBody' => FromBody::class,
        'FromForm' => FromForm::class,
        'FromHeader' => FromHeader::class,
        'FromQuery' => FromQuery::class,
        'FromRoute' => FromRoute::class,
        'FromServices' => FromServices::class,
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

    private array $controllers = [];
    private array $routeTable = [];
    private array $pipes = [];

    private array $baseRouteTemplate = [''];

    private array $routeTemplateArr = [];
    private bool $enableAttributeRouting = false;
    private bool $enableConventionalRouting = false;
    private int $routeType = 2;
    private bool $showRouteTable = false;

    private static Container $diContainer;
    private bool $isProdMode;

    private array $requestDIContainer = [];


    /**
     * App constructor.
     * @throws ReflectionException
     * @throws \Exception
     */
    public function __construct()
    {
//         Initiate DI Container
        self::$diContainer = new Container();
//        read yaml for parameters
        $this->defineConstantsAndParameters();

        //        bootstraps app
        $this->applicationBuilder = new ApplicationBuilder();

        // Initialize OpenTelemetry
        $this->logger = OtelProviderFactory::create(
            'spatial-core',
            '1.0.0',
            getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://collector:4318'
        );
        self::$diContainer->set(LoggerInterface::class, $this->logger);
    }

    public static function diContainer(): Container
    {
        return self::$diContainer;
    }

    /**
     * @throws Exception
     */
    private function defineConstantsAndParameters(): void
    {
        $configDir = getcwd() . DS . 'config' . DS;
//        print_r(getcwd());
        try {
//    config/service.yml
            $services = Yaml::parseFile($configDir . 'services.yaml');
            define('SpatialServices', $services['parameters']);

            //    config/packages/framework.yaml
            $appConfigs = Yaml::parseFile($configDir . DS . 'packages' . DS . 'framework.yaml');
            define('AppConfig', $appConfigs);

            $this->isProdMode = $appConfigs['enableProdMode'];
//    config/packages/doctrine.yaml
            $doctrineConfigs = Yaml::parseFile(
                $configDir . DS . 'packages' . DS . ('doctrine.yaml')
            );
            $doctrineConfigs = $this->resolveEnv($doctrineConfigs);
            define('DoctrineConfig', $doctrineConfigs);
        } catch (ParseException $exception) {
            printf('Unable to parse the YAML string: %s', $exception->getMessage());
        }
    }

    /**
     * @param array $param
     * @return array
     */
    private function resolveEnv(array $param): array
    {
        $param_keys = array_keys($param);

        for ($i = 0, $iMax = count($param); $i < $iMax; $i++) {
//            echo 'param value of ' . $param_keys[$i] . '\n';
//            print_r($param[$param_keys[$i]]);

            if (is_array($param[$param_keys[$i]])) {
                $param[$param_keys[$i]] = $this->resolveEnv($param[$param_keys[$i]]);
            } elseif (is_string($param[$param_keys[$i]]) && str_starts_with($param[$param_keys[$i]], '%env(')) {
//                echo 'getting env variable \n';
                $actualValue = str_replace(array('%env(', ')%'), '', $param[$param_keys[$i]]);
//                print_r($actualValue);

                $param[$param_keys[$i]] = getenv($actualValue);
            }
        }

        return $param;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function showRouteTable(bool $value = true): self
    {
        $this->showRouteTable = $value;
        return $this;
    }

    public function getRouteTable(): string
    {
        $html = '
    <style>
        h2 {
            font-family: Arial, sans-serif;
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        h3, h4 {
            font-family: Arial, sans-serif;
            color: #2c3e50;
            margin-top: 20px;
            margin-bottom: 5px;
            cursor: pointer;
            text-align:center;
        }
        h4 {
            text-align:left;
            }
            
        div {
            font-family: Arial, sans-serif;
            color: #555;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #333;
            background-color: #f9f9f9;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        thead {
            background-color: #e8f5ff;
            text-align: left;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #dfefff;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .params-list {
            font-family: Consolas, monospace;
            font-size: 13px;
            color: #444;
            background-color: #f8f8f8;
            padding: 5px;
            border-radius: 5px;
        }
    </style>
    <h2>API Documentation</h2>
    <div>';

        // Group by modules and controllers
        $modules = [];
        foreach ($this->routeTable as $row) {
            $modules[$row['module']][$row['controller']][] = $row;
        }

        foreach ($modules as $moduleName => $controllers) {
            $module = explode("\\", $moduleName);
            $html .= '
        <div>
            <h3 onclick="toggleSection(\'' . $moduleName . '\')">
                ' . end($module) . '
            </h3>
            <div id="' . $moduleName . '" style="display: block; margin-left: 20px;">';

            foreach ($controllers as $controllerName => $actions) {
                $controller = explode("\\", $controllerName);
                $html .= '
            <h4 onclick="toggleSection(\'' . $moduleName . '_' . $controllerName . '\')">
                😅 ' .end($controller) . '
            </h4>
            <div id="' . htmlspecialchars($moduleName . '_' . $controllerName) . '" style="display: block; margin-left: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Route</th>
                            <th>Params</th>
                            <th>HttpVerb</th>
                            <th>Authorize</th>
                        </tr>
                    </thead>
                    <tbody>';

                foreach ($actions as $action) {
                    $formattedParams = array_map(function ($reflectionParameter) {
                        return '<pre style="margin:0"> <small>#['.$reflectionParameter["bindingSource"].']</small> <span style="background: white">'.$reflectionParameter["param"]->name . ' : <span style="opacity: .5">' . $reflectionParameter["param"]->getType() .'</span></span></pre>';
                    }, $action['params']);

                    $html .= '
                        <tr>
                            <td>' . htmlspecialchars($action['action']) . '</td>
                            <td>' . htmlspecialchars($action['route']) . '</td>
                            <td>' .  (implode('', $formattedParams)) . '</td>
                            <td>' . strtoupper(htmlspecialchars($action['httpMethod'])) . '</td>
                            <td>' . htmlspecialchars(json_encode($action['authGuard'], JSON_THROW_ON_ERROR)) . '</td>
                        </tr>';
                }

                $html .= '</tbody></table>
            </div>';
            }

            $html .= '</div></div>';
        }

        $html .= '
    </div>
    <script>
        function toggleSection(id) {
            var section = document.getElementById(id);
            if (section.style.display === "none") {
                section.style.display = "block";
            } else {
                section.style.display = "none";
            }
        }
    </script>';

        return $html;
    }



    /**
     * Render Results
     */
    public function processX(): ResponseInterface
    {
        return $this->process(new \Spatial\Psr7\Request(), new AppModule);
    }


    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
//        foreach ($this->requestDIContainer as $requestProvider) {
//            try {
//                self::diContainer()->get($requestProvider);
//            } catch (DependencyException|NotFoundException $e) {
//                print_r($e);
//            }
//        }
        $handler->passParams($this->routeTable, self::$diContainer);
        return $handler->handle($request);
    }

    /**
     * @param string $appModule
     * @return App|null expects all params to have an attribute
     * expects all params to have an attribute
     * @throws ReflectionException
     * @throws Exception
     */
    public function boot(string $appModule): void
    {
//        print_r('booting app \n');
//        if ($this->hasColdBooted) {
//            return;
//        }

        $reflectionClass = new ReflectionClass($appModule);
        $reflectionClassApiAttributes = $reflectionClass->getAttributes(ApiModule::class);

        if (count($reflectionClassApiAttributes) === 0) {
            throw ReflectionException();
        }

        $apiModuleAttributes = $reflectionClassApiAttributes[0]->newInstance();


        //        load attribute metadata
        $this->resolveAppModule('root', $apiModuleAttributes);

        //          load configs
        $baseModule = $reflectionClass->newInstance();
        $baseModule->configure($this->applicationBuilder);
        $this->runApp();

//        print_r('booting done \n');
//        print_r(json_encode($this->routeTable));
    }


    /**
     * Make sure to avoid circle imports
     * @param ApiModule $app
     * @return void
     * @throws ReflectionException
     */
    private function resolveAppModule(string $moduleName, ApiModule $app): void
    {
//        find the import with routeModule
        $this->resolveImports($moduleName, $app->imports);

//        Dependency Injection Services
        $this->resolveProviders($moduleName, $app->providers);

//        Declarations
        $this->resolveDeclarations($moduleName, $app->declarations);
//        $routeModule->render();

    }

    /**
     * @param string $moduleName
     * @param array|null $moduleProviders
     * @throws ReflectionException
     */
    private function resolveProviders(string $moduleName, ?array $moduleProviders): void
    {
        if (!$moduleProviders) {
            return;
        }
        //        create provider section
        if (!isset($this->providers[$moduleName])) {
            $this->providers[$moduleName] = [];
        }

        foreach ($moduleProviders as $provider) {
            if (!isset($this->providers[$moduleName][$provider])) {
                // suppose to set it on DI Container
                try {
                    self::$diContainer->get($provider);
//                    check injectable to see if its lifetime or request

                } catch (DependencyException|NotFoundException $e) {
                }
//                record
                $providerReflection = new ReflectionClass($provider);
                $this->providers[$moduleName][$provider] = $providerReflection;

//                $providerInjectableAttribute = $providerReflection->getAttributes(Injectable::class);
//                if ($providerInjectableAttribute[0] && $providerInjectableAttribute[0]->newInstance(
//                    )->providedIn === 'any') {
//                    $this->requestDIContainer[] = $provider;
//                }
            }
        }
    }


    /**
     * @param string $moduleName
     * @param array|null $moduleDeclarations
     * @throws ReflectionException
     */
    private function resolveDeclarations(string $moduleName, ?array $moduleDeclarations): void
    {
        if (!$moduleDeclarations) {
            return;
        }
        //        create declarations section
        if (!isset($this->declarations[$moduleName])) {
            $this->declarations[$moduleName] = [];
        }

        foreach ($moduleDeclarations as $declaration) {
            if (!isset($this->declarations[$moduleName][$declaration])) {
                //            check if it has DI for provider
                $this->declarations[$moduleName][$declaration] = new ReflectionClass($declaration);
            }
        }
    }

    /**
     * @param string $moduleName
     * @param array|null $moduleImports
     * @return void
     * @throws ReflectionException
     */
    private function resolveImports(string $moduleName, ?array $moduleImports): void
    {
        if (!$moduleImports) {
            return;
        }
//        create module section
        if (!isset($this->importModules[$moduleName])) {
            $this->importModules[$moduleName] = [];
        }

//    Loop though imports
        foreach ($moduleImports as $module) {
            if (isset($this->importModules[$moduleName][$module])) {
                throw new \RuntimeException('Import Module ' . $module . ' is already imported in module');
            }

//            check if it has ApiModule attr
            $reflectionClass = new ReflectionClass($module);
            $apiModuleAttributes = $reflectionClass->getAttributes(ApiModule::class);
            if (count($apiModuleAttributes) === 0) {
                throw new \RuntimeException(
                    'Import Module ' . $module . ' is not module, Must have #[ApiModule] Attribute'
                );
            }

            //  check if it has DI for provider
            $this->importModules[$moduleName][$module] = new ReflectionClass($module);
//            run throught its declarations and providers to record them
            $apiModuleAttributes = $apiModuleAttributes[0]->newInstance();
            //        load attribute metadata
            $this->resolveAppModule($module, $apiModuleAttributes);
        }
    }

    public function catch(callable $exceptionCallable): void
    {
        $exceptionCallable();
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
    private function getRequestedMethod(): string
    {
        return strtolower(
            $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] :
                $_SERVER['REQUEST_METHOD']
        );
    }

    /**
     *
     * @throws Exception
     */
    private function runApp(): void
    {
        $this->resolveModuleRouting();


        if (count($this->routeTable) === 0) {
            foreach ($this->declarations as $module => $declarations) {
                $this->createRouteTable($module, $declarations);
            }

//            Print Route Table
            if ($this->showRouteTable) {
                $this->printRouteTable();
            }
//            $this->hasColdBooted = true;
        }
    }


    /**
     * @param string $module
     * @param array $declarations
     * @throws Exception
     */
    private
    function createRouteTable(
        string $module,
        array $declarations
    ): void {
//        separating declarations to
//        controllers
//        pipes
//        directive

        foreach ($declarations as $declaration) {
//            echo '<br/> >> Declaration checking is ' . $declaration->getShortName();
            $attr = $declaration->getAttributes(ApiController::class);
            if (count($attr) > 0) {
                $this->registerNewController($module, $declaration);
                // Now try do draw route table

            } else {
//            check parent;
//                    loop through parents
                while ($parent = $declaration->getParentClass()) {
                    if (
                        $parent->getName() === "Spatial\Core\ControllerBase" ||
                        $parent->getName() === "Spatial\Core\Controller"
                    ) {
                        $this->registerNewController($module, $declaration);
                        break;
                    } else {
//                        echo '<br /> checking parent attribute';
                        $parentAttr = $parent->getAttributes(ApiController::class);
                        if (count($parentAttr) > 0) {
                            $this->registerNewController($module, $declaration);
                        }
                        break;
                    }
                }
            }
        }
    }

    /**
     * Print route tables
     */
    private
    function printRouteTable(): void
    {
        echo $this->getRouteTable();
    }

    /**
     * @param ReflectionClass $controllerReflection
     * @throws Exception
     */
    private
    function registerNewController(
        string $moduleName,
        ReflectionClass $controllerReflection
    ): void {
        if (isset($this->controllers[$controllerReflection->getName()])) {
            throw new \RuntimeException('Controller ' . $controllerReflection->getName() . ' cannot be declared twice');
//            return;
        }
        $this->controllers[$controllerReflection->getName()] = $controllerReflection;


        $tokens = [
            'action' => '',
            'area' => $this->getAreaAttribute($controllerReflection->getAttributes(Area::class)) ?? '',
            'controller' => strtolower(str_replace('Controller', '', $controllerReflection->getShortName())),
            'handler' => '',
            'page' => '',
            'httpVerb' => '',
            'module' => $moduleName,
            'authGuard' => $this->getAuthorizationAttribute(
                $controllerReflection->getAttributes(Authorize::class)
            )
        ];

        switch ($this->routeType) {
            case 1:
                $this->registerControllerRoutes($controllerReflection, $tokens);
                break;
            case 2:
//    attribute routing
                $this->registerAttributeRoute($controllerReflection, $tokens);
                break;

            default:
                $this->registerControllerRoutes($controllerReflection, $tokens);
                $this->registerAttributeRoute($controllerReflection, $tokens);
                break;
        }
    }

    /**
     * @param string $routeTemplate
     */
    private
    function convertRouteTemplateToPattern(
        string $routeTemplate
    ): void {
        $this->routeTemplateArr = explode('/', trim(urldecode($routeTemplate), '/'));
        $baseRoute = '';

        $found = false;

//        conventional routing
        foreach ($this->routeTemplateArr as $routeTX) {
            foreach ($this->reservedRoutingNames as $token) {
                if (str_starts_with($routeTX, '{' . $token)) {
//                    echo '<br /> found ' . $token . ' in ' . $routeTX . ' -->' . $controllerName . '<br /> ';
                    $baseRoute .= '[' . $token . ']/';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $baseRoute .= $routeTX . '/';
                $found = false;
            }
        }

        echo $baseRoute . '<br/>';
        $this->baseRouteTemplate[] = $baseRoute;
    }

    /**
     * @param ReflectionClass $controllerReflection
     * @param array $tokens
     */
    private
    function registerControllerRoutes(
        ReflectionClass $controllerReflection,
        array $tokens
    ): void {
        $controllerActions = $controllerReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($controllerActions as $action) {
            if (str_starts_with($action->getName(), '__')) {
//                skip magic methods
                continue;
            }
            $tokens['action'] = $action->getName();

            foreach ($this->baseRouteTemplate as $baseRoute) {
                $this->setToRouteTable(
                    $controllerReflection->getName(),
                    $baseRoute,
                    $tokens,
                    $action
                );
            }
        }
    }

    /**
     * @param ReflectionClass $controllerReflection
     */
    private
    function registerAttributeRoute(
        ReflectionClass $controllerReflection,
        array $tokens
    ): void {
        $controllerBaseRoute = [''];
        $controllerRoutes = [];
        $controllerRouteAttributes = $controllerReflection->getAttributes(Route::class);
        $controllerActions = $controllerReflection->getMethods(ReflectionMethod::IS_PUBLIC);

//        print_r(
//            [
//                $controllerReflection->getName(),
//                $controllerName,
//                $controllerArea
//            ]
//        );

        if (count($controllerRouteAttributes) > 0) {
            $controllerBaseRoute = [];
            foreach ($controllerRouteAttributes as $baseRouteReflectionAttributes) {
                $routeInstance = $baseRouteReflectionAttributes->newInstance();
                $controllerBaseRoute[] = $routeInstance->template;
            }
        }

        foreach ($controllerActions as $action) {
            if (str_starts_with($action->getName(), '__')) {
                continue;
            }
            $tokens['action'] = $action->getName();

            $tokens['httpVerb'] = $this->getHttpVerbsFromMethods($action);

            $actionRouteReflectionAttributes = $action->getAttributes(Route::class);
            if (count($actionRouteReflectionAttributes) > 0) {
                foreach ($actionRouteReflectionAttributes as $routeReflectionAttribute) {
                    $routeInstance = $routeReflectionAttribute->newInstance();
                    if (str_starts_with($routeInstance->template, '/')) {
                        $this->setToRouteTable(
                            $controllerReflection->getName(),
                            $routeInstance->template,
                            $tokens,
                            $action
                        );
                    } else {
                        foreach ($controllerBaseRoute as $baseRoute) {
                            $this->setToRouteTable(
                                $controllerReflection->getName(),
                                $baseRoute . $routeInstance->template,
                                $tokens,
                                $action
                            );
                        }
                    }
                }
            } else {
//            now also check if method/action has httpverbs without any route, map it to the table
//                $actionRouteReflectionAttributes = $action->getAttributes(Route::class);
                foreach ($controllerBaseRoute as $baseRoute) {
                    $this->setToRouteTable(
                        $controllerReflection->getName(),
                        $baseRoute,
                        $tokens,
                        $action
                    );
                }
            }
        }
    }

    /**
     * @param array $areaAttribute
     * @return string|null
     */
    private
    function getAreaAttribute(
        array $areaAttribute
    ): ?string {
        if (count($areaAttribute) === 0) {
            return null;
        }
        return $areaAttribute[0]->newInstance()->name;
    }


    /**
     * @param array $authorizationAttributes
     * @return array|null
     */
    private
    function getAuthorizationAttribute(
        array $authorizationAttributes
    ): ?array {
        $authAttributes = [];
        if (count($authorizationAttributes) === 0) {
            return null;
        }

        foreach ($authorizationAttributes as $auth) {
            $authGuards = $auth->newInstance()->authGuards;
            foreach ($authGuards as $authGuard) {
                $authAttributes[] = $authGuard;
            }
        }
        return $authAttributes;
    }


    /**
     * @param string $controllerClassName
     * @param string $template
     * @param array $tokens
     * @param ReflectionMethod|null $action
     */
    private
    function setToRouteTable(
        string $controllerClassName,
        string $template,
        array $tokens,
        ?ReflectionMethod $action = null
    ): void {
        $template = trim($template, '/');
//        echo 'setting to route table';
//        check for action area attribute. if it exists, overwrite else none
        $tokens['area'] = $this->getAreaAttribute($action->getAttributes(Area::class)) ?? $tokens['area'];
//        authorization on mehtods

        $actionAuth = $this->getAuthorizationAttribute(
            $action->getAttributes(Authorize::class)
        );
        if ($actionAuth) {
            if ($tokens['authGuard']) {
                $tokens['authGuard'] = array_merge($tokens['authGuard'], $actionAuth);
            } else {
                $tokens['authGuard'] = $actionAuth;
            }
        }

//        go through httpverbs for routing and request methods
        if (count($tokens['httpVerb']) > 0) {
            foreach ($tokens['httpVerb'] as $http) {
                $routeTemplate = $this->replaceTemplateTokens(
                    $http['template'] ? $template . '/' . $http['template'] : $template,
                    $tokens
                );
                $routeSegments = count(explode('/', $routeTemplate));
//                print_r($http);
                $this->routeTable[] = [
                    'routeSegments' => $routeSegments,
                    'route' => $routeTemplate,
                    'controller' => $controllerClassName,
                    'httpMethod' => $http['event'], // $action ? $this->getHttpVerbsFromMethods($action) : null,
                    'action' => $tokens['action'],
                    'params' => $this->getActionParamsWithAttribute($action),
                    'authGuard' => $tokens['authGuard'],
                    'module' => $tokens['module'],
                ];
            }
            return;
        }

        $routeTemplate = $this->replaceTemplateTokens($template, $tokens);
        $routeSegments = count(explode('/', $routeTemplate));
        $this->routeTable[] = [
            'routeSegments' => $routeSegments,
            'route' => $routeTemplate,
            'controller' => $controllerClassName,
            'httpMethod' => $this->setDefaultHttpMethod($tokens['action']),
            // $action ? $this->getHttpVerbsFromMethods($action) : null,
            'action' => $tokens['action'],
            'params' => $this->getActionParamsWithAttribute($action),
            'authGuard' => $tokens['authGuard'],
            'module' => $tokens['module'],
        ];
    }


    /**
     * @param ReflectionMethod|null $action
     * @return array|null
     */
    private function getActionParamsWithAttribute(?ReflectionMethod $action): ?array
    {
        if ($action === null) {
            return null;
        }
        $params = [];

        foreach ($action->getParameters() as $parameter) {
            $actionParam = [
                'param' => $parameter,
                'bindingSource' => null
            ];
            foreach (
                $this->reservedBindingSourceAttributes as $bindName
            => $bindingSource
            ) {
                $attribute = $parameter->getAttributes($bindingSource);
                if (count($attribute) > 0) {
                    $actionParam['bindingSource'] = $bindName;
                    break;
                }
            }

            $actionParam['bindingSource'] ??= 'FromRoute';
            $params[] = $actionParam;
        }

        return $params;
    }

    /**
     * @param string $actionName
     * @return string
     */
    private
    function setDefaultHttpMethod(
        string $actionName
    ): string {
        return match ($actionName) {
            'httpGet' => 'get',
            'httpPost' => 'post',
            'httpPut' => 'put',
            'httpDelete' => 'delete',
            'httpPatch' => 'patch',
            'httpHead' => 'head',
            default => 'all'
        };
    }

    /**
     * @param string $template
     * @param array $tokens
     * @return string
     */
    private
    function replaceTemplateTokens(
        string $template,
        array $tokens
    ): string {
//                replace any part that is reserved with [...];
        foreach ($this->reservedRoutingNames as $tokenKey) {
            if ($tokens[$tokenKey] === null) {
                continue;
            }
            $template = str_replace('[' . $tokenKey . ']', $tokens[$tokenKey], $template);
        }
        return '/' . trim(strtolower($template), '/');
    }

    /**
     * @param ReflectionMethod $action
     * @return array
     */
    private
    function getHttpVerbsFromMethods(
        ReflectionMethod $action
    ): array {
        $verbs = [];
        $params = [];


        foreach ($this->httpVerbs as $httpMethod) {
            $params = [];
            $httpGetReflection = $action->getAttributes($httpMethod);

            if (count($httpGetReflection) > 0) {
//                $verbs[$httpMethod] = [];

                foreach ($httpGetReflection as $verb) {
                    $verbInstance = $verb->newInstance();
                    $verbClassReflection = new ReflectionClass($verbInstance);

                    foreach ($verbClassReflection->getProperties() as $property) {
                        $params[$property->getName()] = $verbInstance->{$property->getName()};
                    }

                    $verbs[] = $params;
                }
            }
        }

        return $verbs;
    }


    private
    function resolveModuleRouting(): void
    {
//        get routing settings
        foreach ($this->applicationBuilder->routingType as $routeEndpoint) {
            if (!$routeEndpoint->useAttributeRouting) {
                //        prepare baseRoute
//        To be moved to application builder to use endpoints
                $this->convertRouteTemplateToPattern($routeEndpoint->pattern);
                $this->enableConventionalRouting = true;
                print_r($routeEndpoint->name);
            } else {
                $this->enableAttributeRouting = true;
            }
        }


        if ($this->enableAttributeRouting & $this->enableConventionalRouting) {
            $this->routeType = 0;
        } elseif (!$this->enableAttributeRouting & $this->enableConventionalRouting) {
            $this->routeType = 1;
        } elseif ($this->enableAttributeRouting & !$this->enableConventionalRouting) {
            $this->routeType = 2;
        }
    }


}

