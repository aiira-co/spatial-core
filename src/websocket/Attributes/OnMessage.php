<?php

declare(strict_types=1);

namespace Spatial\WebSocket\Attributes;

use Attribute;

/**
 * OnMessage Attribute
 * 
 * Marks a method to handle WebSocket message events.
 * Can optionally filter by message type.
 * 
 * @example #[OnMessage]
 * @example #[OnMessage(type: 'chat')]
 * 
 * @package Spatial\WebSocket\Attributes
 */
#[Attribute(Attribute::TARGET_METHOD)]
class OnMessage
{
    public function __construct(
        public ?string $type = null
    ) {}
}
