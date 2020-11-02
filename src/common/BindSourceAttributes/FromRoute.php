<?php


namespace Spatial\Common\BindSourceAttributes;

use Attribute;
use Spatial\Common\BindingSource;

/**
 * Class FromService
 * Route data from the current request
 * @package Spatial\Common\BindSourceAttributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromRoute extends BindingSource
{

}