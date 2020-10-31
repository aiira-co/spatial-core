<?php

declare(strict_types=1);

namespace Spatial\Common\HttpAttributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpGet
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpGet extends HttpMethodAttribute
{
    public string $event = 'get';

}