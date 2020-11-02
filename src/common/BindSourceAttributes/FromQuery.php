<?php


namespace Spatial\Common\BindSourceAttributes;

use Attribute;
use Spatial\Common\BindingSource;

/**
 * Class FromService
 * Request query string parameter
 * @package Spatial\Common\BindSourceAttributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromQuery extends BindingSource
{

}