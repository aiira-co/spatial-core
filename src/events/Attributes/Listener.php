<?php

declare(strict_types=1);

namespace Spatial\Events\Attributes;

use Attribute;

/**
 * Listener Attribute
 * 
 * Marks a class as an event listener for a specific event.
 * 
 * @example #[Listener(OrderCreatedEvent::class)]
 * 
 * @package Spatial\Events\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Listener
{
    /**
     * @param string $event The fully qualified event class name
     * @param int $priority Higher priority listeners are called first
     * @param bool $async Whether to handle the event asynchronously
     */
    public function __construct(
        public string $event,
        public int $priority = 0,
        public bool $async = false
    ) {}
}
