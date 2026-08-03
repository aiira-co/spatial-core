<?php


namespace Spatial\Core;


use DI\Container;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionException;
use Spatial\Common\Processor\MiddlewareProcessor;
use Spatial\Core\Attributes\ApiModule;
use Spatial\Core\DI\ScopedContainer;
use Spatial\Core\Interfaces\ApplicationBuilderInterface;

/**
 * Class App
 * @package Spatial\Core
 */
class App implements MiddlewareInterface
{
    private ApplicationBuilderInterface $applicationBuilder;

    private ConfigurationLoader $configLoader;
    private ModuleRegistrar $moduleRegistrar;
    private RouteTableBuilder $routeTableBuilder;
    private RouteTableRenderer $routeTableRenderer;

    /**
     * Providers hold @Injectables for module declarations, keyed by module name.
     * @var array<string, array<string, \ReflectionClass>>
     */
    private static array $providers = [];

    private array $routeTable = [];
    private bool $showRouteTable = false;

    public static Container $diContainer;
    private bool $isProdMode;
    private ?RouteCache $routeCache = null;

    /**
     * @throws ReflectionException
     * @throws \Exception
     */
    public function __construct()
    {
        self::$diContainer = new ScopedContainer();

        $this->configLoader = new ConfigurationLoader();
        $this->routeTableBuilder = new RouteTableBuilder();
        $this->routeTableRenderer = new RouteTableRenderer();

        $configDir = getcwd() . DIRECTORY_SEPARATOR . 'config';
        $config = $this->configLoader->load($configDir);
        $this->configLoader->defineConstants($config);
        $this->isProdMode = $config['isProdMode'];

        $cacheDir = getcwd() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';
        $this->routeCache = new RouteCache($cacheDir, $this->isProdMode);

        $this->moduleRegistrar = new ModuleRegistrar(self::$diContainer);
        $this->applicationBuilder = new ApplicationBuilder();
    }

    public static function diContainer(): Container
    {
        return self::$diContainer;
    }

    public function showRouteTable(bool $value = true): self
    {
        $this->showRouteTable = $value;
        return $this;
    }

    public function getRouteTable(): string
    {
        return $this->routeTableRenderer->render($this->routeTable);
    }

    public function processX(): ResponseInterface
    {
        return $this->process(new \Spatial\Psr7\Request(), new AppModule);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $container = self::$diContainer;
        if ($container instanceof ScopedContainer) {
            $container->beginRequest();
        }

        try {
            $handler->passParams($this->routeTable, $container);
            return self::pipeMiddleware('root')->process($request, $handler);
        } finally {
            if ($container instanceof ScopedContainer) {
                $container->endRequest();
            }
        }
    }

    public static function pipeMiddleware(string $module): MiddlewareProcessor
    {
        return new MiddlewareProcessor(self::getModuleProvider($module));
    }

    private static function getModuleProvider(string $module): array
    {
        return self::$providers[$module] ?? [];
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function boot(string $appModule): void
    {
        $reflectionClass = new ReflectionClass($appModule);
        $reflectionClassApiAttributes = $reflectionClass->getAttributes(ApiModule::class);

        if (count($reflectionClassApiAttributes) === 0) {
            throw new ReflectionException(
                "Class {$appModule} must have #[ApiModule] attribute."
            );
        }

        $apiModuleAttributes = $reflectionClassApiAttributes[0]->newInstance();

        $this->moduleRegistrar->registerModule('root', $apiModuleAttributes);

        $baseModule = $reflectionClass->newInstance();
        $baseModule->configure($this->applicationBuilder);
        $this->runApp();
    }

    public function catch(callable $exceptionCallable): void
    {
        $exceptionCallable();
    }

    /**
     * @throws Exception
     */
    private function runApp(): void
    {
        if ($this->routeCache !== null) {
            $cachedRoutes = $this->routeCache->getCached();
            if ($cachedRoutes !== null) {
                $this->routeTable = $cachedRoutes;
                self::$providers = $this->moduleRegistrar->getAllProviders();
                return;
            }
        }

        if (count($this->routeTable) === 0) {
            $declarations = $this->moduleRegistrar->getDeclarations();
            if ($declarations === []) {
                throw new Exception(
                    'No module declarations registered; cannot build route table.'
                );
            }

            $this->routeTable = $this->routeTableBuilder->build($declarations);
            $this->routeCache?->cache($this->routeTable);

            if ($this->showRouteTable) {
                $this->printRouteTable();
            }
        }

        self::$providers = $this->moduleRegistrar->getAllProviders();
    }

    private function printRouteTable(): void
    {
        if (php_sapi_name() === 'cli') {
            fwrite(STDOUT, $this->routeTableRenderer->renderText($this->routeTable));
        }
    }
}
