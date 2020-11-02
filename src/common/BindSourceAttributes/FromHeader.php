<?php


namespace Spatial\Common\BindSourceAttributes;

use Attribute;
use Spatial\Common\BindingSource;

/**
 * Class FromService
 * Request header
 * @package Spatial\Common\BindSourceAttributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromHeader extends BindingSource
{

}