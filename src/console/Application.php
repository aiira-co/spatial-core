<?php

declare(strict_types=1);

namespace Spatial\Console;

/**
 * Spatial CLI Application
 * 
 * Command-line interface for code generation and project management.
 * 
 * @package Spatial\Console
 */
class Application
{
    private array $commands = [];
    private string $name = 'Spatial CLI';
    private string $version = '1.0.0';

    public function __construct()
    {
        $this->registerDefaultCommands();
    }

    /**
     * Register default commands.
     * 
     * Only runtime utilities are registered here.
     * Development commands are in spatial/cli package.
     */
    private function registerDefaultCommands(): void
    {
        // Runtime utilities (stay in core)
        $this->register(new Commands\RouteListCommand());
        $this->register(new Commands\RouteCacheCommand());
        $this->register(new Commands\CacheClearCommand());
        $this->register(new Commands\ConfigCacheCommand());
        $this->register(new Commands\QueueWorkCommand());
    }

    /**
     * Register a command.
     */
    public function register(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * Run the CLI application.
     * 
     * @param array $argv Command line arguments
     * @return int Exit code (0 = success)
     */
    public function run(array $argv): int
    {
        array_shift($argv); // Remove script name

        if (empty($argv)) {
            $this->showHelp();
            return 0;
        }

        $commandName = array_shift($argv);

        if ($commandName === '--help' || $commandName === '-h') {
            $this->showHelp();
            return 0;
        }

        if ($commandName === '--version' || $commandName === '-v') {
            $this->output("{$this->name} v{$this->version}");
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            $this->output("Command '{$commandName}' not found.", 'error');
            $this->output("Run 'php spatial --help' to see available commands.");
            return 1;
        }

        try {
            $command = $this->commands[$commandName];
            $args = $this->parseArguments($argv);
            return $command->execute($args, $this);
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Parse command line arguments into associative array.
     */
    private function parseArguments(array $argv): array
    {
        $args = ['_positional' => []];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $args[$parts[0]] = $parts[1] ?? true;
            } elseif (str_starts_with($arg, '-')) {
                $args[substr($arg, 1)] = true;
            } else {
                $args['_positional'][] = $arg;
            }
        }

        return $args;
    }

    /**
     * Show help message.
     */
    private function showHelp(): void
    {
        $this->output("\n{$this->name} v{$this->version}");
        $this->output(str_repeat('=', 40));
        $this->output("\nUsage: php spatial <command> [arguments]\n");
        $this->output("Available commands:");

        foreach ($this->commands as $name => $command) {
            $this->output(sprintf("  %-20s %s", $name, $command->getDescription()));
        }

        $this->output("\nOptions:");
        $this->output("  --help, -h         Show this help message");
        $this->output("  --version, -v      Show version");
        $this->output("");
    }

    /**
     * Output a message to the console.
     */
    public function output(string $message, string $type = 'info'): void
    {
        $prefix = match ($type) {
            'error' => "\033[31m✗ ",    // Red
            'success' => "\033[32m✓ ",   // Green
            'warning' => "\033[33m⚠ ",   // Yellow
            default => ""
        };
        $suffix = $type !== 'info' ? "\033[0m" : "";
        
        fwrite($type === 'error' ? STDERR : STDOUT, $prefix . $message . $suffix . PHP_EOL);
    }

    /**
     * Ask for confirmation.
     */
    public function confirm(string $question): bool
    {
        $this->output($question . " [y/N] ", 'info');
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        return strtolower(trim($line)) === 'y';
    }
}
