<?php

declare(strict_types=1);

namespace Spatial\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

/**
 * HealthCheck
 * 
 * Built-in health check endpoint for Kubernetes and load balancers.
 * Returns status of database, cache, and other services.
 * 
 * @package Spatial\Core
 */
class HealthCheck
{
    private array $checks = [];
    private bool $healthy = true;

    /**
     * Add a health check.
     *
     * @param string $name Check name
     * @param callable $check Function that returns true if healthy
     * @return self
     */
    public function addCheck(string $name, callable $check): self
    {
        $this->checks[$name] = $check;
        return $this;
    }

    /**
     * Run all health checks and return response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        
        return match (true) {
            str_ends_with($path, '/health/live') => $this->liveness(),
            str_ends_with($path, '/health/ready') => $this->readiness(),
            default => $this->fullHealth()
        };
    }

    /**
     * Liveness probe - is the app running?
     */
    public function liveness(): ResponseInterface
    {
        return $this->jsonResponse([
            'status' => 'alive',
            'timestamp' => date('c')
        ], 200);
    }

    /**
     * Readiness probe - is the app ready to receive traffic?
     */
    public function readiness(): ResponseInterface
    {
        $results = $this->runChecks();
        $ready = !in_array(false, array_column($results, 'healthy'));

        return $this->jsonResponse([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $results,
            'timestamp' => date('c')
        ], $ready ? 200 : 503);
    }

    /**
     * Full health check with details.
     */
    public function fullHealth(): ResponseInterface
    {
        $results = $this->runChecks();
        $healthy = !in_array(false, array_column($results, 'healthy'));

        $response = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'version' => $this->getAppVersion(),
            'uptime' => $this->getUptime(),
            'memory' => [
                'used' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true))
            ],
            'php_version' => PHP_VERSION,
            'checks' => $results,
            'timestamp' => date('c')
        ];

        return $this->jsonResponse($response, $healthy ? 200 : 503);
    }

    /**
     * Run all registered checks.
     */
    private function runChecks(): array
    {
        $results = [];

        foreach ($this->checks as $name => $check) {
            $start = microtime(true);
            try {
                $healthy = $check();
                $results[$name] = [
                    'healthy' => (bool)$healthy,
                    'latency_ms' => round((microtime(true) - $start) * 1000, 2)
                ];
            } catch (\Exception $e) {
                $results[$name] = [
                    'healthy' => false,
                    'error' => $e->getMessage(),
                    'latency_ms' => round((microtime(true) - $start) * 1000, 2)
                ];
            }
        }

        return $results;
    }

    private function jsonResponse(array $data, int $status): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getAppVersion(): string
    {
        $composerPath = getcwd() . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            return $composer['version'] ?? '1.0.0';
        }
        return '1.0.0';
    }

    private function getUptime(): string
    {
        if (defined('APP_START_TIME')) {
            $seconds = time() - APP_START_TIME;
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $mins = floor(($seconds % 3600) / 60);
            return "{$days}d {$hours}h {$mins}m";
        }
        return 'N/A';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Create default health check with common checks.
     */
    public static function create(): self
    {
        $health = new self();

        // Add default checks
        $health->addCheck('app', fn() => true);

        return $health;
    }

    /**
     * Add database check.
     */
    public function withDatabase(callable $getConnection): self
    {
        $this->addCheck('database', function () use ($getConnection) {
            $conn = $getConnection();
            if (method_exists($conn, 'ping')) {
                return $conn->ping();
            }
            if (method_exists($conn, 'getConnection')) {
                return $conn->getConnection()->ping();
            }
            return true;
        });

        return $this;
    }

    /**
     * Add cache check.
     */
    public function withCache(callable $getCache): self
    {
        $this->addCheck('cache', function () use ($getCache) {
            $cache = $getCache();
            $key = '_health_check_' . time();
            $cache->set($key, 'ok', 5);
            $result = $cache->get($key) === 'ok';
            $cache->delete($key);
            return $result;
        });

        return $this;
    }

    /**
     * Add custom check.
     */
    public function with(string $name, callable $check): self
    {
        return $this->addCheck($name, $check);
    }
}
