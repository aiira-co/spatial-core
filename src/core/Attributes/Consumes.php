<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Bind
 * Specifies data types that an action accepts.
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Consumes
{
    public function __construct(public string $contentType)
    {
    }
}