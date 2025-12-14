<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Route Cache Command
 * 
 * Caches the route table for production performance.
 * 
 * @example php spatial route:cache
 * 
 * @package Spatial\Console\Commands
 */
class RouteCacheCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'route:cache';
    }

    public function getDescription(): string
    {
        return 'Cache route table for production';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $cacheDir = $this->getBasePath() . '/var/cache';
        $cacheFile = "{$cacheDir}/routes.cache.php";

        $this->ensureDirectory($cacheDir);

        $this->output("Generating route cache...");

        // Build route table
        $routes = $this->buildRouteTable();

        if (empty($routes)) {
            $this->warning("No routes found to cache.");
            return 0;
        }

        // Generate cache file
        $cacheContent = $this->generateCacheFile($routes);

        if ($this->writeFile($cacheFile, $cacheContent)) {
            $this->success("Route cache generated: {$cacheFile}");
            $this->output("  Routes cached: " . count($routes));
            return 0;
        }

        $this->error("Failed to generate route cache");
        return 1;
    }

    private function buildRouteTable(): array
    {
        $routes = [];
        $presentationPath = $this->getBasePath() . '/src/presentation';

        if (!is_dir($presentationPath)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($presentationPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && str_contains($file->getPathname(), 'Controller')) {
                $controllerRoutes = $this->extractRoutes($file->getPathname());
                $routes = array_merge($routes, $controllerRoutes);
            }
        }

        return $routes;
    }

    private function extractRoutes(string $filePath): array
    {
        $routes = [];
        $content = file_get_contents($filePath);

        if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            return [];
        }
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return [];
        }

        $fqcn = $nsMatch[1] . '\\' . $classMatch[1];

        if (!class_exists($fqcn)) {
            require_once $filePath;
        }

        if (!class_exists($fqcn)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($fqcn);
            
            if (empty($reflection->getAttributes('Spatial\\Core\\Attributes\\ApiController'))) {
                return [];
            }

            $baseRoute = $this->getBaseRoute($reflection);
            
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $methodRoutes = $this->extractMethodRoutes($reflection, $method, $baseRoute);
                $routes = array_merge($routes, $methodRoutes);
            }
        } catch (\Exception $e) {
            // Skip this controller
        }

        return $routes;
    }

    private function getBaseRoute(\ReflectionClass $reflection): string
    {
        $route = '';
        $area = '';

        $areaAttrs = $reflection->getAttributes('Spatial\\Core\\Attributes\\Area');
        if (!empty($areaAttrs)) {
            $area = $areaAttrs[0]->getArguments()[0] ?? '';
        }

        $routeAttrs = $reflection->getAttributes('Spatial\\Core\\Attributes\\Route');
        if (!empty($routeAttrs)) {
            $route = $routeAttrs[0]->getArguments()[0] ?? $routeAttrs[0]->getArguments()['template'] ?? '';
        }

        $controllerName = strtolower(str_replace('Controller', '', $reflection->getShortName()));
        $route = str_replace('[controller]', $controllerName, $route);
        $route = str_replace('[area]', $area, $route);

        return '/' . trim($route, '/');
    }

    private function extractMethodRoutes(\ReflectionClass $controller, \ReflectionMethod $method, string $baseRoute): array
    {
        $routes = [];
        $httpVerbs = [
            'Spatial\\Common\\HttpAttributes\\HttpGet' => 'GET',
            'Spatial\\Common\\HttpAttributes\\HttpPost' => 'POST',
            'Spatial\\Common\\HttpAttributes\\HttpPut' => 'PUT',
            'Spatial\\Common\\HttpAttributes\\HttpPatch' => 'PATCH',
            'Spatial\\Common\\HttpAttributes\\HttpDelete' => 'DELETE',
        ];

        foreach ($httpVerbs as $attrClass => $verb) {
            $attrs = $method->getAttributes($attrClass);
            foreach ($attrs as $attr) {
                $template = $attr->getArguments()[0] ?? '';
                $fullRoute = $baseRoute . ($template ? '/' . $template : '');
                $fullRoute = str_replace('//', '/', $fullRoute);

                $routes[] = [
                    'method' => $verb,
                    'route' => $fullRoute,
                    'controller' => $controller->getName(),
                    'action' => $method->getName()
                ];
            }
        }

        return $routes;
    }

    private function generateCacheFile(array $routes): string
    {
        $routesExport = var_export($routes, true);
        
        return <<<PHP
<?php
/**
 * Route Cache
 * Generated: {$this->now()}
 * Routes: {$this->count($routes)}
 * 
 * Do not edit this file manually.
 * Regenerate with: php spatial route:cache
 */

return {$routesExport};
PHP;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function count(array $routes): int
    {
        return count($routes);
    }
}
