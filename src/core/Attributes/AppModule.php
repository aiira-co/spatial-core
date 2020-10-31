<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class AppModule
 * @package Spatial\Core
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AppModule
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