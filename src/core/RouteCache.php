<?php

declare(strict_types=1);

namespace Spatial\Core;

/**
 * Route Cache
 * 
 * Caches compiled route tables for production environments
 * to avoid re-parsing controller attributes on every boot.
 * 
 * @package Spatial\Core
 */
class RouteCache
{
    private string $cacheFile;

    /**
     * @param string $cacheDir Directory to store route cache
     * @param bool $isProduction Whether to use caching (should be true in production)
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly bool $isProduction = false
    ) {
        $this->cacheFile = rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'routes.cache.php';
    }

    /**
     * Get cached route table if available.
     * 
     * @return array|null Cached route table or null if not cached/not in production
     */
    public function getCached(): ?array
    {
        if (!$this->isProduction) {
            return null;
        }

        if (!file_exists($this->cacheFile)) {
            return null;
        }

        try {
            $cached = require $this->cacheFile;
            
            if (!is_array($cached) || !isset($cached['routes'], $cached['timestamp'])) {
                return null;
            }

            return $cached['routes'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cache the route table for future use.
     * 
     * @param array $routeTable The compiled route table to cache
     * @return bool Whether caching succeeded
     */
    public function cache(array $routeTable): bool
    {
        if (!$this->isProduction) {
            return false;
        }

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                error_log("[Spatial] Failed to create cache directory: {$this->cacheDir}");
                return false;
            }
        }

        // Serialize route table (excluding non-serializable ReflectionParameter objects)
        $serializableRoutes = $this->makeSerializable($routeTable);

        $content = "<?php\n\n// Spatial Route Cache - Generated " . date('Y-m-d H:i:s') . "\n// Do not edit manually\n\nreturn " . var_export([
            'timestamp' => time(),
            'routes' => $serializableRoutes
        ], true) . ";\n";

        $result = file_put_contents($this->cacheFile, $content, LOCK_EX);

        if ($result === false) {
            error_log("[Spatial] Failed to write route cache to: {$this->cacheFile}");
            return false;
        }

        // Set restrictive permissions
        chmod($this->cacheFile, 0644);

        return true;
    }

    /**
     * Clear the route cache.
     * 
     * @return bool Whether clearing succeeded
     */
    public function clear(): bool
    {
        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }
        return true;
    }

    /**
     * Check if route cache exists and is valid.
     * 
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->isProduction && file_exists($this->cacheFile);
    }

    /**
     * Get cache file modification time.
     * 
     * @return int|null Unix timestamp or null if not cached
     */
    public function getCacheTime(): ?int
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        return filemtime($this->cacheFile) ?: null;
    }

    /**
     * Convert route table to serializable format by removing ReflectionParameter objects.
     * 
     * @param array $routeTable
     * @return array
     */
    private function makeSerializable(array $routeTable): array
    {
        return array_map(function (array $route): array {
            // Convert params array - ReflectionParameter is not serializable
            if (isset($route['params']) && is_array($route['params'])) {
                $route['params'] = array_map(function (array $param): array {
                    if (isset($param['param']) && $param['param'] instanceof \ReflectionParameter) {
                        $reflectionParam = $param['param'];
                        $param['param'] = [
                            'name' => $reflectionParam->getName(),
                            'type' => $reflectionParam->getType()?->getName(),
                            'optional' => $reflectionParam->isOptional(),
                            'defaultValue' => $reflectionParam->isOptional() && $reflectionParam->isDefaultValueAvailable()
                                ? $reflectionParam->getDefaultValue()
                                : null,
                            'allowsNull' => $reflectionParam->allowsNull(),
                        ];
                    }
                    return $param;
                }, $route['params']);
            }
            return $route;
        }, $routeTable);
    }
}
