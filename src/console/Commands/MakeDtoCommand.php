<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Make DTO Command
 * 
 * Generates a new Data Transfer Object with validation attributes.
 * 
 * @example php spatial make:dto CreateUserDto --module=Identity --entity=User
 * @example php spatial make:dto UpdateProductDto --module=App --entity=Product
 * 
 * @package Spatial\Console\Commands
 */
class MakeDtoCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'make:dto';
    }

    public function getDescription(): string
    {
        return 'Create a new DTO class with validation';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        if (empty($args['_positional'])) {
            $this->error("Please provide a DTO name.");
            $this->output("Usage: php spatial make:dto <name> [--module=<Module>] [--entity=<Entity>]");
            $this->output("Example: php spatial make:dto CreateUserDto --module=Identity --entity=User");
            return 1;
        }

        $name = $this->toPascalCase($args['_positional'][0]);
        if (!str_ends_with($name, 'Dto')) {
            $name .= 'Dto';
        }

        $module = $args['module'] ?? 'App';
        $entity = $args['entity'] ?? null;
        
        $module = $this->toPascalCase($module);
        $entity = $entity ? $this->toPascalCase($entity) : null;

        $content = $this->generateDto($name, $module, $entity);
        
        // Place DTO near the CQRS commands/queries if entity is specified
        if ($entity) {
            $path = $this->getBasePath() . "/src/core/Application/Logics/{$module}/{$entity}/Dtos/{$name}.php";
        } else {
            $path = $this->getBasePath() . "/src/core/Application/Dtos/{$module}/{$name}.php";
        }
        
        if (file_exists($path)) {
            $this->error("DTO already exists: {$path}");
            return 1;
        }

        $this->ensureDirectory(dirname($path));

        if ($this->writeFile($path, $content)) {
            $this->success("Created DTO: {$path}");
            return 0;
        }

        $this->error("Failed to create DTO");
        return 1;
    }

    private function generateDto(string $name, string $module, ?string $entity): string
    {
        $namespace = $entity 
            ? "Core\\Application\\Logics\\{$module}\\{$entity}\\Dtos"
            : "Core\\Application\\Dtos\\{$module}";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Spatial\\Common\\ValidationAttributes\\Required;
use Spatial\\Common\\ValidationAttributes\\Email;
use Spatial\\Common\\ValidationAttributes\\MinLength;
use Spatial\\Common\\ValidationAttributes\\MaxLength;
use Spatial\\Common\\ValidationAttributes\\Range;

/**
 * {$name}
 * 
 * Data Transfer Object with validation attributes.
 */
class {$name}
{
    #[Required]
    #[MaxLength(255)]
    public string \$name;
    
    // Add more properties with validation attributes as needed.
    // Examples:
    //
    // #[Required]
    // #[Email]
    // public string \$email;
    //
    // #[Required]
    // #[MinLength(8)]
    // public string \$password;
    //
    // #[Range(0, 99999)]
    // public ?float \$price = null;
}
PHP;
    }
}
