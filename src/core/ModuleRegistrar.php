<?php

declare(strict_types=1);

namespace Spatial\Core;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use ReflectionClass;
use ReflectionException;
use Spatial\Core\Attributes\ApiModule;
use Spatial\Core\Attributes\Injectable;
use Spatial\Core\DI\InjectableScope;
use Spatial\Core\DI\ScopedContainer;

/**
 * Module Registrar
 *
 * Handles registration of modules, providers, declarations, and imports
 * in the dependency injection container.
 *
 * @package Spatial\Core
 */
class ModuleRegistrar
{
    /**
     * Registered providers by module.
     * @var array<string, array<string, ReflectionClass>>
     */
    private array $providers = [];

    /**
     * Registered declarations by module.
     * @var array<string, array<string, ReflectionClass>>
     */
    private array $declarations = [];

    /**
     * Imported modules by parent module.
     * @var array<string, array<string, ReflectionClass>>
     */
    private array $importModules = [];

    public function __construct(
        private readonly Container $container
    ) {}

    /**
     * Register a module and all its imports, providers, and declarations.
     *
     * @throws ReflectionException
     */
    public function registerModule(string $moduleName, ApiModule $moduleAttributes): void
    {
        $this->registerImports($moduleName, $moduleAttributes->imports);
        $this->registerProviders($moduleName, $moduleAttributes->providers);
        $this->registerDeclarations($moduleName, $moduleAttributes->declarations);
    }

    /**
     * @param array|null $moduleProviders Provider class names
     * @throws ReflectionException
     */
    public function registerProviders(string $moduleName, ?array $moduleProviders): void
    {
        if (!$moduleProviders) {
            return;
        }

        if (!isset($this->providers[$moduleName])) {
            $this->providers[$moduleName] = [];
        }

        foreach ($moduleProviders as $providerClassName) {
            if (isset($this->providers[$moduleName][$providerClassName])) {
                continue;
            }

            $reflection = new ReflectionClass($providerClassName);
            $requestScoped = $this->isRequestScopedProvider($reflection);

            if ($requestScoped && $this->container instanceof ScopedContainer) {
                $this->container->markRequestScoped($providerClassName);
            }

            // Eagerly resolve root/platform providers so boot-time wiring and
            // pool warmup run once per worker. Request-scoped providers are
            // deferred until the first request that needs them.
            if (!$requestScoped) {
                try {
                    $this->container->get($providerClassName);
                } catch (DependencyException|NotFoundException $e) {
                    error_log(
                        "[Spatial] Warning: Could not pre-register provider '{$providerClassName}': " . $e->getMessage()
                    );
                }
            }

            $this->providers[$moduleName][$providerClassName] = $reflection;
        }
    }

    private function isRequestScopedProvider(ReflectionClass $reflection): bool
    {
        $attributes = $reflection->getAttributes(Injectable::class);
        if ($attributes === []) {
            return false;
        }

        $injectable = $attributes[0]->newInstance();

        return InjectableScope::isRequestScoped($injectable->providedIn);
    }

    /**
     * @param array|null $moduleDeclarations Declaration class names
     * @throws ReflectionException
     */
    public function registerDeclarations(string $moduleName, ?array $moduleDeclarations): void
    {
        if (!$moduleDeclarations) {
            return;
        }

        if (!isset($this->declarations[$moduleName])) {
            $this->declarations[$moduleName] = [];
        }

        foreach ($moduleDeclarations as $declaration) {
            if (!isset($this->declarations[$moduleName][$declaration])) {
                $this->declarations[$moduleName][$declaration] = new ReflectionClass($declaration);
            }
        }
    }

    /**
     * @param array|null $moduleImports Import module class names
     * @throws ReflectionException
     */
    public function registerImports(string $moduleName, ?array $moduleImports): void
    {
        if (!$moduleImports) {
            return;
        }

        if (!isset($this->importModules[$moduleName])) {
            $this->importModules[$moduleName] = [];
        }

        foreach ($moduleImports as $module) {
            if (isset($this->importModules[$moduleName][$module])) {
                throw new \RuntimeException(
                    "Import Module '{$module}' is already imported in '{$moduleName}'"
                );
            }

            $reflectionClass = new ReflectionClass($module);
            $apiModuleAttributes = $reflectionClass->getAttributes(ApiModule::class);

            if (count($apiModuleAttributes) === 0) {
                throw new \RuntimeException(
                    "Import Module '{$module}' is not a module. Must have #[ApiModule] attribute."
                );
            }

            $this->importModules[$moduleName][$module] = $reflectionClass;
            $this->registerModule($module, $apiModuleAttributes[0]->newInstance());
        }
    }

    /**
     * @return array<string, array<string, ReflectionClass>>
     */
    public function getDeclarations(): array
    {
        return $this->declarations;
    }

    /**
     * @return array<string, ReflectionClass>
     */
    public function getModuleProviders(string $moduleName): array
    {
        return $this->providers[$moduleName] ?? [];
    }

    /**
     * @return array<string, array<string, ReflectionClass>>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }
}
