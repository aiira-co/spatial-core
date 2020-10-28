<?php


declare(strict_types=1);

namespace Spatial\Core;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public string $event = 'controller';
}