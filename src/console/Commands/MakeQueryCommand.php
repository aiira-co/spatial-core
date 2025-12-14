<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make Query Command
 * 
 * Generates a new CQRS Query and Handler following spatial's structure.
 * 
 * @example php spatial make:query GetUsers --module=Identity --entity=User
 * @example php spatial make:query GetProducts --module=App --entity=Product
 * 
 * @package Spatial\Console\Commands
 */
class MakeQueryCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:query';
    }

    public function getDescription(): string
    {
        return 'Create a new CQRS query and handler';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a query name.");
            $this->output("Usage: php spatial make:query <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:query GetUsers --module=Identity --entity=User");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);

        // Module and entity are required
        $module = $args['module'] ?? null;
        $entity = $args['entity'] ?? null;

        if ($module === null || $entity === null) {
            $this->error("Both --module and --entity parameters are required.");
            $this->output("Usage: php spatial make:query <name> --module=<Module> --entity=<Entity>");
            $this->output("Example: php spatial make:query GetProducts --module=App --entity=Product");
            return 1;
        }

        $module = $this->toPascalCase($module);
        $entity = $this->toPascalCase($entity);

        $queryContent = $this->generateQuery($name, $module, $entity);
        $handlerContent = $this->generateHandler($name, $module, $entity);
        
        $basePath = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Queries";
        
        $queryPath = "{$basePath}/{$name}.php";
        $handlerPath = "{$basePath}/{$name}Handler.php";

        if (file_exists($queryPath)) {
            $this->error("Query already exists: {$queryPath}");
            return 1;
        }

        $this->ensureDirectory($basePath);

        $this->writeFile($queryPath, $queryContent);
        $this->success("Created query: {$queryPath}");
        
        $this->writeFile($handlerPath, $handlerContent);
        $this->success("Created handler: {$handlerPath}");

        return 0;
    }

    private function generateQuery(string $name, string $module, string $entity): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Queries;

use Spatial\\Psr7\\Request;

/**
 * {$name} Query
 * 
 * Request to fetch data, passed to its Handler.
 */
class {$name} extends Request
{
    public object \$data;

    /**
     * Execute the query logic.
     * Called by the handler.
     * 
     * @return mixed
     */
    public function fetchData(): mixed
    {
        // TODO: Implement query logic
        // Access query params via \$this->data
        
        // Example pagination
        \$page = \$this->data->page ?? 1;
        \$limit = \$this->data->limit ?? 10;
        
        return [
            'items' => [],
            'page' => \$page,
            'limit' => \$limit,
            'total' => 0
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

namespace Core\\Application\\Logics\\{$module}\\{$entity}\\Queries;

use Psr\\Http\\Message\\ResponseInterface;
use Psr\\Http\\Message\\ServerRequestInterface;
use Spatial\\Psr7\\RequestHandler;

/**
 * {$name} Handler
 * 
 * Handles the {$name} query and returns response.
 */
class {$name}Handler extends RequestHandler
{
    /**
     * Handle the query request.
     *
     * @param ServerRequestInterface \$request The {$name} request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface \$request): ResponseInterface
    {
        // Execute query logic
        \$data = \$request->fetchData();
        
        // Build response
        \$payload = json_encode(\$data, JSON_THROW_ON_ERROR);
        \$this->response->getBody()->write(\$payload);
        
        return \$this->response
            ->withHeader('Content-Type', 'application/json');
    }
}
PHP;
    }
}
