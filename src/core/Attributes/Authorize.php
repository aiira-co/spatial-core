<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Bind
 * Specifies prefix and properties to include for model binding.
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Authorize
{
    public array $authGuards;

    public function __construct(object|string ...$authGuards)
    {
        $this->authGuards = $authGuards;
    }
}