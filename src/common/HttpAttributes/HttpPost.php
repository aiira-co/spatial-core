<?php

declare(strict_types=1);

namespace Spatial\Common\HttpAttributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpPost
 * Identifies an action that supports the HTTP POST action verb.
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpPost extends HttpMethodAttribute
{
    public string $event = 'created';
}