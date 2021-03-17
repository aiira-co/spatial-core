<?php

declare(strict_types=1);


namespace Spatial\Api;

use Spatial\Api\ArticleApi\ArticleApiModule;
use Spatial\Api\StoreApi\StoreApiModule;
use Spatial\Api\StoreApi\Controllers\ProductController;
use Spatial\Api\StoreApi\Controllers\TestController;
use Spatial\Api\StoreApi\Controllers\ValuesController;
use Spatial\Common\CommonModule;
use Spatial\Core\Attributes\ApiModule;
use Spatial\Core\Interfaces\IApplicationBuilder;
use Spatial\Core\Interfaces\IWebHostEnvironment;
use Spatial\Router\Interfaces\IRouteBuilder;
use Spatial\Router\RouteBuilder;


#[ApiModule(
    imports: [
    StoreApiModule::class,
    ArticleApiModule::class
],
    declarations: [],
    providers: [],
    /**
     * Bootstrap controller must contain an index() for bootstrap
     */
    bootstrap: [ValuesController::class]
)]
class TestModule
{
    /**
     * Method is called for app configuration
     * configure routing here
     * @param IApplicationBuilder $app
     * @param IWebHostEnvironment|null $env
     */
    public function configure(IApplicationBuilder $app, ?IWebHostEnvironment $env = null): void
    {
//        if ($env->isDevelopment()) {
//            $app->useDeveloperExceptionPage();
//        }

//        $endpoints = new RouteBuilder();


        $app->useHttpsRedirection();

        $app->useRouting();

        $app->useAuthorization();

        $app->useEndpoints(
            fn(IRouteBuilder $endpoints) => [
                $endpoints->mapControllers()
            ]

        );
    }


}