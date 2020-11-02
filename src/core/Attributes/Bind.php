<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Bind
 * Specifies prefix and properties to include for model binding.
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Bind
{
}