<?php
declare(strict_types=1);

namespace Spatial\Core;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiModule
{


    public function __construct(
        public array $imports,
        public array $controllers,
        public ?array $providers = null,
        public ?array $bootstrap = null
    ) {
    }


}