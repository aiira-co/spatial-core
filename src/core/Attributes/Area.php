<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Area
 * @package Spatial\Core\Attributes
 */
#[Attribute]
class Area
{
    public string $event;

    public function __construct(
        public string $name
    ) {
        $this->event = 'area';
    }

}