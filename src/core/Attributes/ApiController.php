<?php


declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class ApiController
 * The [ApiController] attribute can be applied to a controller class to enable the following opinionated, API-specific behaviors:
 * - Attribute routing requirement
 * - Automatic HTTP 400 responses
 * - Binding source parameter inference
 * - Multipart/form-data request inference
 * - Problem details for error status codes
 * @package Spatial\Core\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiController
{
    public string $event = 'controller';
}