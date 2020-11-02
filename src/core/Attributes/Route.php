<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class Route
 * Specifies URL pattern for a controller or action.
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route extends HttpMethodAttribute
{
}