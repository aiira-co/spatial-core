<?php

declare(strict_types=1);

namespace Spatial\WebSocket\Attributes;

use Attribute;

/**
 * OnConnect Attribute
 * 
 * Marks a method to handle WebSocket connection events.
 * 
 * @example #[OnConnect]
 * 
 * @package Spatial\WebSocket\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnConnect
{
    public function __construct() {}
}
