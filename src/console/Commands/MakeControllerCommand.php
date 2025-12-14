<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Controller Command
 * 
 * Generates a new API controller and registers it in the module.
 * 
 * @example php spatial make:controller User --module=IdentityApi
 * @example php spatial make:controller Product --module=WebApi
 * 
 * @package Spatial\Console\Commands
 */
class MakeControllerCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a new controller class in a module';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a controller name.");
            $this->output("Usage: php spatial make:controller <name> --module=<ModuleName>");
            $this->output("Example: php spatial make:controller User --module=IdentityApi");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        // Module is required
        $module = $args['module'] ?? null;
        if ($module === null) {
            $this->error("--module parameter is required.");
            $this->output("Usage: php spatial make:controller <name> --module=<ModuleName>");
            $this->output("Example: php spatial make:controller Product --module=WebApi");
            return 1;
        }

        $module = $this->toPascalCase($module);

        // Generate controller
        $content = $this->generateController($name, $module);
        
        $controllerDir = $this->getBasePath() . "/src/presentation/{$module}/Controllers";
        $controllerPath = "{$controllerDir}/{$name}.php";
        
        if (file_exists($controllerPath)) {
            $this->error("Controller already exists: {$controllerPath}");
            return 1;
        }

        // Ensure Controllers directory exists
        $this->ensureDirectory($controllerDir);

        if (!$this->writeFile($controllerPath, $content)) {
            $this->error("Failed to create controller");
            return 1;
        }

        $this->success("Created controller: {$controllerPath}");

        // Auto-register in module
        $registered = $this->registerInModule($name, $module);
        if ($registered) {
            $this->success("Registered in {$module}Module.php");
        } else {
            $this->output("Note: Could not auto-register. Please add to {$module}Module.php manually.");
        }

        return 0;
    }

    private function generateController(string $name, string $module): string
    {
        $shortName = str_replace('Controller', '', $name);
        $routeName = strtolower($shortName);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Presentation\\{$module}\\Controllers;

use Psr\\Http\\Message\\ResponseInterface;
use Spatial\\Core\\Attributes\\ApiController;
use Spatial\\Core\\Attributes\\Route;
use Spatial\\Core\\ControllerBase;
use Spatial\\Common\\HttpAttributes\\HttpGet;
use Spatial\\Common\\HttpAttributes\\HttpPost;
use Spatial\\Common\\HttpAttributes\\HttpPut;
use Spatial\\Common\\HttpAttributes\\HttpDelete;
use Spatial\\Common\\BindSourceAttributes\\FromBody;
use Spatial\\Common\\BindSourceAttributes\\FromQuery;

#[ApiController]
#[Route('[controller]')]
class {$name} extends ControllerBase
{
    /**
     * GET /{$routeName}
     */
    #[HttpGet]
    public function index(): ResponseInterface
    {
        return \$this->ok([
            'message' => '{$shortName} list'
        ]);
    }

    /**
     * GET /{$routeName}/{id}
     */
    #[HttpGet('{id:int}')]
    public function show(int \$id): ResponseInterface
    {
        return \$this->ok([
            'id' => \$id,
            'message' => '{$shortName} details'
        ]);
    }

    /**
     * POST /{$routeName}
     */
    #[HttpPost]
    public function create(#[FromBody] array \$data): ResponseInterface
    {
        // TODO: Cast \$data to DTO and validate
        // \$dto = Caster::cast(Create{$shortName}Dto::class, \$data);
        // if (\$error = \$this->validate(\$dto)) return \$error;
        
        return \$this->created(\$data);
    }

    /**
     * PUT /{$routeName}/{id}
     */
    #[HttpPut('{id:int}')]
    public function update(int \$id, #[FromBody] array \$data): ResponseInterface
    {
        return \$this->ok(['id' => \$id, 'updated' => true]);
    }

    /**
     * DELETE /{$routeName}/{id}
     */
    #[HttpDelete('{id:int}')]
    public function delete(int \$id): ResponseInterface
    {
        return \$this->noContent();
    }
}
PHP;
    }

    /**
     * Register controller in module file.
     */
    private function registerInModule(string $controllerName, string $module): bool
    {
        $modulePath = $this->getBasePath() . "/src/presentation/{$module}/{$module}Module.php";
        
        if (!file_exists($modulePath)) {
            return false;
        }

        $content = file_get_contents($modulePath);
        
        // Check if already registered
        if (str_contains($content, "{$controllerName}::class")) {
            return true; // Already registered
        }

        // Add use statement
        $useStatement = "use Presentation\\{$module}\\Controllers\\{$controllerName};";
        if (!str_contains($content, $useStatement)) {
            // Find last use statement and add after it
            $content = preg_replace(
                '/(use [^;]+;\n)(?!use )/',
                "$1{$useStatement}\n",
                $content,
                1
            );
        }

        // Add to declarations array
        $content = preg_replace(
            '/(declarations:\s*\[\s*)(\n?\s*)([^\]]*)(])/',
            "$1$2$3    {$controllerName}::class,\n$4",
            $content
        );

        return file_put_contents($modulePath, $content) !== false;
    }
}
