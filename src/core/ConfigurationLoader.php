<?php

declare(strict_types=1);

namespace Spatial\Core;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Loader
 * 
 * Handles loading and parsing of YAML configuration files
 * and environment variable resolution.
 * 
 * @package Spatial\Core
 */
class ConfigurationLoader
{
    private bool $isProdMode = false;

    /**
     * Load all configuration files.
     * 
     * @param string $configDir Path to config directory
     * @return array{isProdMode: bool, services: array, app: array, doctrine: array}
     * @throws \Exception If configuration files cannot be parsed
     */
    public function load(string $configDir): array
    {
        $configDir = rtrim($configDir, '/\\') . DIRECTORY_SEPARATOR;

        try {
            // Load services.yaml
            $services = Yaml::parseFile($configDir . 'services.yaml');
            
            // Load framework.yaml
            $appConfigs = Yaml::parseFile(
                $configDir . 'packages' . DIRECTORY_SEPARATOR . 'framework.yaml'
            );
            $appConfigs['enableProdMode'] = self::resolveEnableProdMode(
                $appConfigs['enableProdMode'] ?? false
            );
            $this->isProdMode = $appConfigs['enableProdMode'];

            // Load doctrine.yaml with env resolution
            $doctrineConfigs = Yaml::parseFile(
                $configDir . 'packages' . DIRECTORY_SEPARATOR . 'doctrine.yaml'
            );
            $doctrineConfigs = $this->resolveEnv($doctrineConfigs);

            return [
                'isProdMode' => $this->isProdMode,
                'services' => $services['parameters'] ?? [],
                'app' => $appConfigs,
                'doctrine' => $doctrineConfigs,
            ];
        } catch (ParseException $exception) {
            throw new \Exception(
                sprintf('Unable to parse YAML configuration: %s', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * Define global constants from configuration.
     * 
     * @param array $config Configuration array from load()
     */
    public function defineConstants(array $config): void
    {
        if (!defined('SpatialServices')) {
            define('SpatialServices', $config['services']);
        }
        if (!defined('AppConfig')) {
            define('AppConfig', $config['app']);
        }
        if (!defined('DoctrineConfig')) {
            define('DoctrineConfig', $config['doctrine']);
        }
    }

    /**
     * Get whether production mode is enabled.
     */
    public function isProdMode(): bool
    {
        return $this->isProdMode;
    }

    /**
     * Derive enableProdMode from APP_ENV when set.
     *
     * Rule: any APP_ENV other than "development" is production mode.
     * When APP_ENV is unset/empty, the framework.yaml boolean is used as fallback.
     */
    public static function resolveEnableProdMode(mixed $yamlFallback): bool
    {
        $appEnv = getenv('APP_ENV');
        if ($appEnv === false || trim((string) $appEnv) === '') {
            return (bool) $yamlFallback;
        }

        return strtolower(trim((string) $appEnv)) !== 'development';
    }

    /**
     * Recursively resolve environment variables in config values.
     * 
     * Replaces %env(VAR_NAME)% with the value of getenv('VAR_NAME')
     * 
     * @param array $param Configuration array
     * @return array Resolved configuration
     */
    public function resolveEnv(array $param): array
    {
        foreach ($param as $key => $value) {
            if (is_array($value)) {
                $param[$key] = $this->resolveEnv($value);
            } elseif (is_string($value) && str_starts_with($value, '%env(')) {
                // Extract variable name from %env(VAR_NAME)%
                $envVar = preg_replace('/^%env\(([^)]+)\)%$/', '$1', $value);
                $param[$key] = getenv($envVar) ?: '';
            }
        }

        return $param;
    }
}
