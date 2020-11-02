<?php

declare(strict_types=1);

namespace Spatial\Common;

use Spatial\Core\Attributes\ApiModule;

/**
 * Class CommonModule
 * Exports all the basic Spatial directives and pipes, such as NgIf, NgForOf, DecimalPipe, and so on. Re-exported by BrowserModule, which is included automatically in the root ApiModule when you create a new app with the CLI new command.
 *
 * The providers options configure the NgModule's injector to provide localization dependencies to members.
 * The exports options make the declared directives and pipes available for import by other NgModules.
 * @package Spatial\Common
 */
#[ApiModule(
    imports: [],
    declarations: [],
    exports: []
)]
class CommonModule
{

}