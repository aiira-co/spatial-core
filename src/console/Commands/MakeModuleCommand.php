<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Module Command
 * 
 * Generates a new API module with folder structure and auto-registers in AppModule.
 * 
 * @example php spatial make:module PaymentsApi
 * @example php spatial make:module NotificationsApi
 * 
 * @package Spatial\Console\Commands
 */
class MakeModuleCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:module';
    }

    public function getDescription(): string
    {
        return 'Create a new API module with folder structure';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a module name.");
            $this->output("Usage: php spatial make:module <name>");
            $this->output("Example: php spatial make:module PaymentsApi");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        
        // Ensure proper naming
        if (!str_ends_with($name, 'Api') && !str_ends_with($name, 'Module')) {
            $name .= 'Api';
        }

        $moduleClassName = $name . 'Module';
        if (str_ends_with($name, 'Module')) {
            $moduleClassName = $name;
            $name = substr($name, 0, -6); // Remove 'Module' suffix for folder name
        }

        // Create folder structure
        $moduleDir = $this->getBasePath() . "/src/presentation/{$name}";
        $controllersDir = "{$moduleDir}/Controllers";
        $modulePath = "{$moduleDir}/{$moduleClassName}.php";

        if (file_exists($modulePath)) {
            $this->error("Module already exists: {$modulePath}");
            return 1;
        }

        // Create directories
        $this->ensureDirectory($controllersDir);
        $this->success("Created directory: {$controllersDir}");

        // Generate module file
        $content = $this->generateModule($name, $moduleClassName);
        if (!$this->writeFile($modulePath, $content)) {
            $this->error("Failed to create module file");
            return 1;
        }
        $this->success("Created module: {$modulePath}");

        // Auto-register in AppModule
        $registered = $this->registerInAppModule($name, $moduleClassName);
        if ($registered) {
            $this->success("Registered in AppModule.php");
        } else {
            $this->output("Note: Could not auto-register. Please add to AppModule.php imports manually:");
            $this->output("  imports: [{$moduleClassName}::class]");
        }

        $this->output("");
        $this->output("Next steps:");
        $this->output("  php spatial make:controller Example --module={$name}");

        return 0;
    }

    private function generateModule(string $name, string $moduleClassName): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Presentation\\{$name};

use Spatial\\Core\\Attributes\\ApiModule;

#[ApiModule(
    imports: [],
    declarations: [],
    providers: [],
    bootstrap: []
)]
class {$moduleClassName}
{
    /**
     * Configure module services and middleware.
     * Optional - remove if not needed.
     */
    // public function configure(ApplicationBuilderInterface \$app): void
    // {
    //     // Add module-specific configuration
    // }
}
PHP;
    }

    /**
     * Register module in AppModule.php
     */
    private function registerInAppModule(string $name, string $moduleClassName): bool
    {
        $appModulePath = $this->getBasePath() . "/src/presentation/AppModule.php";
        
        if (!file_exists($appModulePath)) {
            return false;
        }

        $content = file_get_contents($appModulePath);
        
        // Check if already registered
        if (str_contains($content, "{$moduleClassName}::class")) {
            return true;
        }

        // Add use statement after existing use statements
        $useStatement = "use Presentation\\{$name}\\{$moduleClassName};";
        if (!str_contains($content, $useStatement)) {
            // Find the last use statement and add after it
            if (preg_match('/^(use [^;]+;\s*)+/m', $content, $matches, PREG_OFFSET_SET)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr($content, 0, $insertPos) . $useStatement . "\n" . substr($content, $insertPos);
            }
        }

        // Add to imports array
        // Look for imports: [ and add before the closing ]
        $content = preg_replace(
            '/(imports:\s*\[\s*\n?)(\s*)([^\]]*?)(])/',
            "$1$2$3$2    {$moduleClassName}::class,\n$4",
            $content
        );

        return file_put_contents($appModulePath, $content) !== false;
    }
}
