<?php

declare(strict_types=1);

namespace Spatial\Core;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use ReflectionClass;
use ReflectionException;
use Spatial\Core\Attributes\ApiModule;

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
     * @param string $moduleName Module identifier
     * @param ApiModule $moduleAttributes Module attribute instance
     * @throws ReflectionException
     */
    public function registerModule(string $moduleName, ApiModule $moduleAttributes): void
    {
        // Register imports first (they may provide dependencies)
        $this->registerImports($moduleName, $moduleAttributes->imports);

        // Register providers for DI
        $this->registerProviders($moduleName, $moduleAttributes->providers);

        // Register declarations (controllers, etc.)
        $this->registerDeclarations($moduleName, $moduleAttributes->declarations);
    }

    /**
     * Register providers for a module.
     * 
     * @param string $moduleName Module identifier
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

            // Pre-register in DI container
            try {
                $this->container->get($providerClassName);
            } catch (DependencyException|NotFoundException $e) {
                error_log(
                    "[Spatial] Warning: Could not pre-register provider '{$providerClassName}': " . $e->getMessage()
                );
            }

            $this->providers[$moduleName][$providerClassName] = new ReflectionClass($providerClassName);
        }
    }

    /**
     * Register declarations (controllers, pipes, etc.) for a module.
     * 
     * @param string $moduleName Module identifier
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
     * Register imported modules.
     * 
     * @param string $moduleName Parent module identifier
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

            // Recursively register the imported module
            $this->registerModule($module, $apiModuleAttributes[0]->newInstance());
        }
    }

    /**
     * Get all declarations across all modules.
     * 
     * @return array<string, array<string, ReflectionClass>>
     */
    public function getDeclarations(): array
    {
        return $this->declarations;
    }

    /**
     * Get providers for a specific module.
     * 
     * @param string $moduleName Module identifier
     * @return array<string, ReflectionClass>
     */
    public function getModuleProviders(string $moduleName): array
    {
        return $this->providers[$moduleName] ?? [];
    }

    /**
     * Get all providers across all modules.
     * 
     * @return array<string, array<string, ReflectionClass>>
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }
}
