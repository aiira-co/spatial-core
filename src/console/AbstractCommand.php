<?php

declare(strict_types=1);

namespace Spatial\Console;

/**
 * Abstract Command
 * 
 * Base class for CLI commands with helper methods.
 * 
 * @package Spatial\Console
 */
abstract class AbstractCommand implements CommandInterface
{
    protected Application $app;

    /**
     * Output a message.
     */
    protected function output(string $message, string $type = 'info'): void
    {
        $this->app->output($message, $type);
    }

    /**
     * Output a success message.
     */
    protected function success(string $message): void
    {
        $this->output($message, 'success');
    }

    /**
     * Output an error message.
     */
    protected function error(string $message): void
    {
        $this->output($message, 'error');
    }

    /**
     * Get base path for file generation.
     */
    protected function getBasePath(): string
    {
        return getcwd();
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Write a file with content.
     */
    protected function writeFile(string $path, string $content): bool
    {
        $dir = dirname($path);
        $this->ensureDirectory($dir);
        return file_put_contents($path, $content) !== false;
    }

    /**
     * Convert a name to PascalCase.
     */
    protected function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * Convert a name to camelCase.
     */
    protected function toCamelCase(string $name): string
    {
        return lcfirst($this->toPascalCase($name));
    }
}
