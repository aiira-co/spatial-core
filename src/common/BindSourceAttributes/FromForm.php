<?php


namespace Spatial\Common\BindSourceAttributes;

use Attribute;
use Spatial\Common\BindingSource;

/**
 * Class FromForm
 * Form data in the request body
 * @package Spatial\Common\BindSourceAttributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromForm extends BindingSource
{

}