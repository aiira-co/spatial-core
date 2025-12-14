<?php

declare(strict_types=1);

namespace Spatial\WebSocket\Attributes;

use Attribute;

/**
 * WebSocketController Attribute
 * 
 * Marks a class as a WebSocket controller.
 * 
 * @example #[WebSocketController('/chat')]
 * 
 * @package Spatial\WebSocket\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WebSocketController
{
    public function __construct(
        public string $path = '/'
    ) {}
}
