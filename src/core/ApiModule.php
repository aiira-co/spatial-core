<?php

declare(strict_types=1);

namespace Spatial\Core;

use Attribute;

/**
 * Class ApiModule
 * @package Spatial\Core
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiModule
{


    public function __construct(
        public array $imports,
        public array $declarations,
        public array $exports,
        /**
         * Register Services for DI
         * @var array|null
         */
        public ?array $providers = null,
        public ?array $bootstrap = null
    ) {
    }


}