<?php

declare(strict_types=1);

namespace Spatial\Console;

/**
 * Command Interface
 * 
 * All CLI commands must implement this interface.
 * 
 * @package Spatial\Console
 */
interface CommandInterface
{
    /**
     * Get the command name (e.g., 'make:controller').
     */
    public function getName(): string;

    /**
     * Get the command description.
     */
    public function getDescription(): string;

    /**
     * Execute the command.
     * 
     * @param array $args Parsed arguments
     * @param Application $app The CLI application instance
     * @return int Exit code (0 = success)
     */
    public function execute(array $args, Application $app): int;
}
