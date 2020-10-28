<?php

declare(strict_types=1);

namespace Spatial\Common\Http;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpPut
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpPut extends HttpMethodAttribute
{
    public string $event = 'update';
}