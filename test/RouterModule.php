<?php

declare(strict_types=1);

namespace Spatial\Api;

use Spatial\Core\ApiModule;
use Spatial\Router\RouteBuilder;

$route = new RouteBuilder();

$routes = [
    $route->mapRoute(
        name: $this->api,
        pattern: $this->apiUri . '/{controller}/public/{id:int}',
        defaults: new class () {
                public int $id = 3;
                public string $content;

                public function __construct()
                {
                    $this->content = file_get_contents('php://input');
                }
            }
    ),
    $route->mapRoute(
        'SuiteApi',
        'suite-api/{controller}/public/{id}',
        new class () {
            public int $id = 3;
            public string $content;

            public function __construct()
            {
                $this->content = file_get_contents('php://input');
            }
        }
    )
];

#[ApiModule()]
class RouterModule
{
    /**
     * Method is called for app configuration
     * configure routing here
     * @param $app
     */
    public function configure($app):void
    {

    }
}
