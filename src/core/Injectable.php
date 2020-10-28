<?php

declare(strict_types=1);

namespace Spatial\Core;

use Attribute;

/**
 * Class Injectable
 * @package Spatial\Core
 * Injectable Attribute for DI Service
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{


    public function __construct() {
    }


}