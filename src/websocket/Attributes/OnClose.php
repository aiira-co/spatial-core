<?php

declare(strict_types=1);

namespace Spatial\WebSocket\Attributes;

use Attribute;

/**
 * OnClose Attribute
 * 
 * Marks a method to handle WebSocket close events.
 * 
 * @example #[OnClose]
 * 
 * @package Spatial\WebSocket\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnClose
{
    public function __construct() {}
}
