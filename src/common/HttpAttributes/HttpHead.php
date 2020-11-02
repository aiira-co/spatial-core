<?php

declare(strict_types=1);

namespace Spatial\Common\HttpAttributes;

use Attribute;
use Spatial\Core\HttpMethodAttribute;

/**
 * Class HttpHead
 * Identifies an action that supports the HTTP HEAD action verb.
 * @package Spatial\Common\Http
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class HttpHead extends HttpMethodAttribute
{
    public string $event = 'head';

}