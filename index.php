<?php

require 'vendor/autoload.php';


use Spatial\Api\TestModule;
use Spatial\Core\App;
use Spatial\Router\RouteBuilder;
use Spatial\Router\RouterModule;

//config/environment
//$environmentProduction = true;
//if($environmentProduction){
//    enableProduction(); // cache routes and results to redis/ ram driver
//}

//echo  phpinfo();
//main
$app = new App();
try {
    $app->showRouteTable()
        ->bootstrapModule(TestModule::class)
        ?->catch(fn() => die('error'));
} catch (ReflectionException $e) {
}

//die('testing new with attributes');
//
//
//
//$ri = new RouteBuilder();
//
//$routes = [
//    ... (new Router($ri))->getRoutes()
//];

// echo '<pre>';
// var_dump($routes);


// (new RouterModule)->routeConfig(
//     $routes,
//     [
//         'enableCache'=>true, 
//         'allowedMethod'=>'GET, POST, PUT, DELETE',
//         'CORS'=>['https://client.com', 'localhost:4200', 'localhost:3200']
//     ]
//     );

//$appModule = new RouterModule();
//$appModule->routeConfig(...$routes)
//    ->allowedMethods('GET, POST, PUT, DELETE')
//    ->enableCache(true)
//    ->authGuard()
//    ->defaultContentType('application/json')
//    ->controllerNamespaceMap('Spatial\\{name}\\Controllers\\');
// ->defaultParams('Spatial\\{name}\\Controllers\\');

// echo (new Request)->getBody();

//    $appModule->render();
//} catch (ReflectionException $e) {
//try {
//    http_response_code($e->getCode());
//    echo $e->getMessage();
//}


// $name ='Api';
// $routeTemplate = 'api/{controller}/cat/{id}';
// $defaults = new class(){
//     public $id = 3;
//     public $data;
// };
