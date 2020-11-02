<?php

declare(strict_types=1);

namespace Spatial\Common\HttpAttributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpDelete
 * Identifies an action that supports the HTTP DELETE action verb.
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpDelete extends HttpMethodAttribute
{
    public string $event = 'delete';
}