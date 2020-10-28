<?php

declare(strict_types=1);

namespace Spatial\Common\Http;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpHead
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpHead extends HttpMethodAttribute
{
    public string $event = 'head';

}