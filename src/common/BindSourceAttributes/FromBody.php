<?php


namespace Spatial\Common\BindSourceAttributes;

use Attribute;
use Spatial\Common\BindingSource;

#[Attribute(Attribute::TARGET_PARAMETER)]
class FromBody extends BindingSource
{

}