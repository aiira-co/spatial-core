<?php

declare(strict_types=1);

namespace Spatial\Common\HttpAttributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpPut
 * Identifies an action that supports the HTTP PUT action verb.
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpPut extends HttpMethodAttribute
{
    public string $event = 'update';
}