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
use Spatial\Core\Interfaces\ApplicationBuilderInterface;
use Spatial\Core\Interfaces\WebHostEnvironmentInterface;
use Spatial\Router\Interfaces\RouteBuilderInterface;
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
     * @param ApplicationBuilderInterface $app
     * @param WebHostEnvironmentInterface|null $env
     */
    public function configure(ApplicationBuilderInterface $app, ?WebHostEnvironmentInterface $env = null): void
    {
//        if ($env->isDevelopment()) {
//            $app->useDeveloperExceptionPage();
//        }

//        $endpoints = new RouteBuilder();


        $app->useHttpsRedirection();

        $app->useRouting();

        $app->useAuthorization();

        $app->useEndpoints(
            fn(RouteBuilderInterface $endpoints) => [
                $endpoints->mapControllers()
            ]

        );
    }


}