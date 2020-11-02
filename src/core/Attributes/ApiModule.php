<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class ApiModule
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiModule
{


    public function __construct(
        /**
         * Import AppModels To App
         */
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