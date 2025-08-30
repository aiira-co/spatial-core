<?php
declare(strict_types=1);
namespace Spatial\Telemetry\Attributes;
use Attribute;

/**
 * Class HttpPut
 * Identifies an action that supports the HTTP PUT action verb.
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD)]
class TraceAttribute
{
    public function __construct(
        public ?string $name = null
    ) {
    }
}