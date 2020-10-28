<?php

declare(strict_types=1);

namespace Spatial\Core;

use Attribute;

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