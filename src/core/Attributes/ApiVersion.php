<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * ApiVersion Attribute
 * 
 * Specifies the API version for a controller or method.
 * Used in route generation to prefix routes with version.
 * 
 * @example #[ApiVersion('v1')]
 * @example #[ApiVersion('v2', deprecated: true)]
 * 
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ApiVersion
{
    /**
     * @param string $version The version string (e.g., 'v1', 'v2')
     * @param bool $deprecated Whether this version is deprecated
     * @param string|null $sunset Date when this version will be removed (e.g., '2025-06-01')
     */
    public function __construct(
        public string $version,
        public bool $deprecated = false,
        public ?string $sunset = null
    ) {}
}
