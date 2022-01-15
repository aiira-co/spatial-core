<?php

declare(strict_types=1);

namespace Spatial\Core\Attributes;

use Attribute;

/**
 * Class Injectable
 * @package Spatial\Core\Attributes
 * Injectable Attribute for DI Service
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Injectable
{

    /**
     * Determines which injectors will provide the injectable.
     *
     * - `Type<any>` - associates the injectable with an `@NgModule` or other `InjectorType`,
     * - 'null' : Equivalent to `undefined`. The injectable is not provided in any scope automatically
     * and must be added to a `providers` array of an [@NgModule](api/core/NgModule#providers),
     * [@Component](api/core/Directive#providers) or [@Directive](api/core/Directive#providers).
     *
     * The following options specify that this injectable should be provided in one of the following
     * injectors:
     * - 'root' : The application-level injector in most apps.
     * - 'platform' : A special singleton platform injector shared by all
     * applications on the page.
     * - 'any' : Provides a unique instance in each lazy loaded module while all eagerly loaded
     * modules share one instance.
     *
     */
    public function __construct(public mixed $providedIn = 'root')
    {
    }


}