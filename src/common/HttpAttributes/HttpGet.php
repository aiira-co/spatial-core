<?php

declare(strict_types=1);

namespace Spatial\Common\HttpAttributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpGet
 * Identifies an action that supports the HTTP GET action verb.
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD)]
class HttpGet extends HttpMethodAttribute
{
    public string $event = 'get';

}