<?php


namespace Spatial\Api\ArticleApi;

use Spatial\Api\ArticleApi\Controller\ArticleController;
use Spatial\Core\Attributes\ApiModule;

#[ApiModule(
    imports: [],
    declarations: [
    ArticleController::class
],
    providers: [],
    /**
     * Bootstrap controller must contain an index() for bootstrap
     */
    bootstrap: [ArticleController::class]
)]
class ArticleApiModule
{

}