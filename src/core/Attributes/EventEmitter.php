<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class EventEmitter
 * @package Spatial\Core
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class EventEmitter
{

    public function __construct()
    {
    }


}