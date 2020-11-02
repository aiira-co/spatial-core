<?php


namespace Spatial\Common\BindSourceAttributes;

use Attribute;
use Spatial\Common\BindingSource;

/**
 * Class FromService
 * The request service injected as an action parameter
 * @package Spatial\Common\BindSourceAttributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromServices extends BindingSource
{

}