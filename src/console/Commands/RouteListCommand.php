<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Route List Command
 * 
 * Lists all registered routes in the application.
 * 
 * @example php spatial route:list
 * 
 * @package Spatial\Console\Commands
 */
class RouteListCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'route:list';
    }

    public function getDescription(): string
    {
        return 'List all registered routes';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        // Try to load cached routes
        $cacheDir = $this->getBasePath() . '/var/cache';
        $cacheFile = $cacheDir . '/routes.cache.php';

        if (file_exists($cacheFile)) {
            $cached = require $cacheFile;
            $routes = $cached['routes'] ?? [];
            
            $this->output("\nRegistered Routes (from cache):");
            $this->output(str_repeat('=', 60));
            
            $this->printRoutes($routes);
            return 0;
        }

        $this->output("\nNo cached routes found.");
        $this->output("Run the application once to generate the route cache,");
        $this->output("or check the /api-docs endpoint in the browser.");
        
        return 0;
    }

    private function printRoutes(array $routes): void
    {
        $grouped = [];
        foreach ($routes as $route) {
            $controller = $route['controller'] ?? 'Unknown';
            $grouped[$controller][] = $route;
        }

        foreach ($grouped as $controller => $controllerRoutes) {
            $shortName = $this->getShortName($controller);
            $this->output("\n{$shortName}");
            $this->output(str_repeat('-', 40));

            foreach ($controllerRoutes as $route) {
                $method = strtoupper($route['httpMethod'] ?? 'ALL');
                $path = $route['route'] ?? '/';
                $action = $route['action'] ?? '?';
                $auth = !empty($route['authGuard']) ? '🔒' : '  ';

                $this->output(sprintf(
                    "  %s [%-6s] %-30s -> %s()",
                    $auth,
                    $method,
                    $path,
                    $action
                ));
            }
        }

        $this->output("\n" . str_repeat('=', 60));
        $this->output("Total routes: " . count($routes));
        $this->output("");
    }

    private function getShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
