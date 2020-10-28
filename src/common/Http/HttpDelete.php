<?php

declare(strict_types=1);

namespace Spatial\Common\Http;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpDelete
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpDelete extends HttpMethodAttribute
{
    public string $event = 'delete';
}