<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Command Command
 * 
 * Generates a new CQRS Command and Handler following spatial's structure.
 * 
 * @example php spatial make:command CreateUser --module=Identity --entity=User
 * @example php spatial make:command UpdateProduct --module=App --entity=Product
 * 
 * @package Spatial\Console\Commands
 */
class MakeCommandCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:command';
    }

    public function getDescription(): string
    {
        return 'Create a new CQRS command and handler';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a command name.");
            $this->output("Usage: php spatial make:command <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:command CreateUser --module=Identity --entity=User");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);

        // Module and entity are required
        $module = $args['module'] ?? null;
        $entity = $args['entity'] ?? null;

        if ($module === null || $entity === null) {
            $this->error("Both --module and --entity parameters are required.");
            $this->output("Usage: php spatial make:command <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:command CreateProduct --module=App --entity=Product");
            return 1;
        }

        $module = $this->toPascalCase($module);
        $entity = $this->toPascalCase($entity);

        $commandContent = $this->generateCommand($name, $module, $entity);
        $handlerContent = $this->generateHandler($name, $module, $entity);
        
        $basePath = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Commands";
        
        $commandPath = "{$basePath}/{$name}.php";
        $handlerPath = "{$basePath}/{$name}Handler.php";

        if (file_exists($commandPath)) {
            $this->error("Command already exists: {$commandPath}");
            return 1;
        }

        $this->ensureDirectory($basePath);

        $this->writeFile($commandPath, $commandContent);
        $this->success("Created command: {$commandPath}");
        
        $this->writeFile($handlerPath, $handlerContent);
        $this->success("Created handler: {$handlerPath}");

        return 0;
    }

    private function generateCommand(string $name, string $module, string $entity): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Commands;

use Spatial\\Psr7\\Request;

/**
 * {$name} Command
 * 
 * Request to be passed to its Handler.
 * Handler can use this class's properties and methods.
 */
class {$name} extends Request
{
    public object \$data;

    /**
     * Execute the command logic.
     * Called by the handler.
     * 
     * @return mixed
     */
    public function execute(): mixed
    {
        // TODO: Implement command logic
        // Access request data via \$this->data
        
        return [
            'success' => true,
            'message' => '{$name} executed successfully'
        ];
    }
}
PHP;
    }

    private function generateHandler(string $name, string $module, string $entity): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Commands;

use Psr\\Http\\Message\\ResponseInterface;
use Psr\\Http\\Message\\ServerRequestInterface;
use Spatial\\Psr7\\RequestHandler;

/**
 * {$name} Handler
 * 
 * Handles the {$name} command and returns response.
 */
class {$name}Handler extends RequestHandler
{
    /**
     * Handle the command request.
     *
     * @param ServerRequestInterface \$request The {$name} request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface \$request): ResponseInterface
    {
        // Execute command logic
        \$result = \$request->execute();
        
        // Build response
        \$payload = json_encode(\$result, JSON_THROW_ON_ERROR);
        \$this->response->getBody()->write(\$payload);
        
        return \$this->response
            ->withHeader('Content-Type', 'application/json');
    }
}
PHP;
    }
}
