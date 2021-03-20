<?php

declare(strict_types=1);

namespace Spatial\Core;

//action
//area
//controller
//handler
//page


use Spatial\Core\Interfaces\ApiModuleInterface;

/**
 * Class HttpMethodAttribute
 * @package Spatial\Core
 */
class HttpMethodAttribute implements ApiModuleInterface
{
    public string $event;

    public function __construct(
        public ?string $template = null,
        public ?string $name = null,
        public ?int $order = 0
    ) {
    }
}