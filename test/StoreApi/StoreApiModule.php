<?php


namespace Spatial\Api\StoreApi;

use Spatial\Api\StoreApi\Controllers\ProductController;
use Spatial\Api\StoreApi\Controllers\TestController;
use Spatial\Api\StoreApi\Controllers\ValuesController;
use Spatial\Common\CommonModule;
use Spatial\Core\Attributes\ApiModule;

#[ApiModule(
    imports: [
    CommonModule::class
],
    declarations: [
    ProductController::class,
    TestController::class,
    ValuesController::class
],
    providers: [],
    /**
     * Bootstrap controller must contain an index() for bootstrap
     */
    bootstrap: [ValuesController::class]
)]
class StoreApiModule
{

}