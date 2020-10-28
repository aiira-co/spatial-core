<?php

declare(strict_types=1);

namespace Spatial\Core;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route extends HttpMethodAttribute
{
}