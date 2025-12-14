<?php

declare(strict_types=1);

namespace Spatial\Core;

use ReflectionClass;
use ReflectionMethod;
use Spatial\Common\BindSourceAttributes\FromBody;
use Spatial\Common\BindSourceAttributes\FromForm;
use Spatial\Common\BindSourceAttributes\FromHeader;
use Spatial\Common\BindSourceAttributes\FromQuery;
use Spatial\Common\BindSourceAttributes\FromRoute;
use Spatial\Common\BindSourceAttributes\FromServices;
use Spatial\Common\HttpAttributes\HttpDelete;
use Spatial\Common\HttpAttributes\HttpGet;
use Spatial\Common\HttpAttributes\HttpHead;
use Spatial\Common\HttpAttributes\HttpPatch;
use Spatial\Common\HttpAttributes\HttpPost;
use Spatial\Common\HttpAttributes\HttpPut;
use Spatial\Core\Attributes\ApiController;
use Spatial\Core\Attributes\Area;
use Spatial\Core\Attributes\Authorize;
use Spatial\Core\Attributes\Route;

/**
 * Route Table Builder
 * 
 * Builds the route table by scanning controllers and their attributes
 * to create a mapping of URL patterns to controller actions.
 * 
 * @package Spatial\Core
 */
class RouteTableBuilder
{
    /**
     * HTTP verb attribute mappings.
     */
    private array $httpVerbs = [
        'HttpGet' => HttpGet::class,
        'HttpPost' => HttpPost::class,
        'HttpPut' => HttpPut::class,
        'HttpDelete' => HttpDelete::class,
        'HttpHead' => HttpHead::class,
        'HttpPatch' => HttpPatch::class,
    ];

    /**
     * Reserved routing token names.
     */
    private array $reservedRoutingNames = [
        'action',
        'area',
        'controller',
        'handler',
        'page'
    ];

    /**
     * Binding source attribute mappings.
     */
    private array $bindingSourceAttributes = [
        'FromBody' => FromBody::class,
        'FromForm' => FromForm::class,
        'FromHeader' => FromHeader::class,
        'FromQuery' => FromQuery::class,
        'FromRoute' => FromRoute::class,
        'FromServices' => FromServices::class,
    ];

    /**
     * Built route table.
     */
    private array $routeTable = [];

    /**
     * Registered controllers.
     */
    private array $controllers = [];

    /**
     * Build route table from module declarations.
     * 
     * @param array<string, array<string, ReflectionClass>> $moduleDeclarations
     * @return array The built route table
     */
    public function build(array $moduleDeclarations): array
    {
        $this->routeTable = [];
        $this->controllers = [];

        foreach ($moduleDeclarations as $moduleName => $declarations) {
            $this->processModuleDeclarations($moduleName, $declarations);
        }

        return $this->routeTable;
    }

    /**
     * Get the current route table.
     */
    public function getRouteTable(): array
    {
        return $this->routeTable;
    }

    /**
     * Process declarations for a single module.
     */
    private function processModuleDeclarations(string $moduleName, array $declarations): void
    {
        foreach ($declarations as $declaration) {
            if ($this->isController($declaration)) {
                $this->registerController($moduleName, $declaration);
            }
        }
    }

    /**
     * Check if a declaration is a controller.
     */
    private function isController(ReflectionClass $declaration): bool
    {
        // Check for ApiController attribute
        if (count($declaration->getAttributes(ApiController::class)) > 0) {
            return true;
        }

        // Check parent classes
        $parent = $declaration;
        while ($parent = $parent->getParentClass()) {
            if (in_array($parent->getName(), ['Spatial\\Core\\ControllerBase', 'Spatial\\Core\\Controller'])) {
                return true;
            }
            if (count($parent->getAttributes(ApiController::class)) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register a controller and its routes.
     */
    private function registerController(string $moduleName, ReflectionClass $controllerReflection): void
    {
        $controllerName = $controllerReflection->getName();

        if (isset($this->controllers[$controllerName])) {
            throw new \RuntimeException("Controller '{$controllerName}' cannot be declared twice");
        }

        $this->controllers[$controllerName] = $controllerReflection;

        $tokens = [
            'action' => '',
            'area' => $this->getAreaAttribute($controllerReflection->getAttributes(Area::class)),
            'controller' => strtolower(str_replace('Controller', '', $controllerReflection->getShortName())),
            'handler' => '',
            'page' => '',
            'httpVerb' => '',
            'module' => $moduleName,
            'authGuard' => $this->getAuthorizationAttribute(
                $controllerReflection->getAttributes(Authorize::class)
            )
        ];

        $this->registerAttributeRoutes($controllerReflection, $tokens);
    }

    /**
     * Register routes from controller and method attributes.
     */
    private function registerAttributeRoutes(ReflectionClass $controllerReflection, array $tokens): void
    {
        // Get controller-level route templates
        $controllerBaseRoutes = [''];
        $controllerRouteAttributes = $controllerReflection->getAttributes(Route::class);

        if (count($controllerRouteAttributes) > 0) {
            $controllerBaseRoutes = [];
            foreach ($controllerRouteAttributes as $routeAttr) {
                $controllerBaseRoutes[] = $routeAttr->newInstance()->template;
            }
        }

        // Process each public method
        $methods = $controllerReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $action) {
            if (str_starts_with($action->getName(), '__')) {
                continue; // Skip magic methods
            }

            $tokens['action'] = $action->getName();
            $tokens['httpVerb'] = $this->getHttpVerbsFromMethod($action);

            // Get method-level route attributes
            $actionRouteAttributes = $action->getAttributes(Route::class);

            if (count($actionRouteAttributes) > 0) {
                foreach ($actionRouteAttributes as $routeAttr) {
                    $template = $routeAttr->newInstance()->template;

                    if (str_starts_with($template, '/')) {
                        // Absolute route - ignore controller base
                        $this->addToRouteTable($controllerReflection->getName(), $template, $tokens, $action);
                    } else {
                        // Relative route - combine with controller base
                        foreach ($controllerBaseRoutes as $baseRoute) {
                            $this->addToRouteTable(
                                $controllerReflection->getName(),
                                $baseRoute . $template,
                                $tokens,
                                $action
                            );
                        }
                    }
                }
            } else {
                // No route attribute - use controller base routes
                foreach ($controllerBaseRoutes as $baseRoute) {
                    $this->addToRouteTable($controllerReflection->getName(), $baseRoute, $tokens, $action);
                }
            }
        }
    }

    /**
     * Add a route entry to the route table.
     */
    private function addToRouteTable(
        string $controllerClassName,
        string $template,
        array $tokens,
        ReflectionMethod $action
    ): void {
        $template = trim($template, '/');

        // Override area from method attribute if present
        $tokens['area'] = $this->getAreaAttribute($action->getAttributes(Area::class)) ?? $tokens['area'];

        // Merge method-level authorization
        $actionAuth = $this->getAuthorizationAttribute($action->getAttributes(Authorize::class));
        if ($actionAuth) {
            $tokens['authGuard'] = $tokens['authGuard']
                ? array_merge($tokens['authGuard'], $actionAuth)
                : $actionAuth;
        }

        // Create route entries for each HTTP verb
        if (count($tokens['httpVerb']) > 0) {
            foreach ($tokens['httpVerb'] as $http) {
                $fullTemplate = $http['template']
                    ? $template . '/' . $http['template']
                    : $template;

                $routeTemplate = $this->replaceTemplateTokens($fullTemplate, $tokens);

                $this->routeTable[] = [
                    'routeSegments' => count(explode('/', $routeTemplate)),
                    'route' => $routeTemplate,
                    'controller' => $controllerClassName,
                    'httpMethod' => $http['event'],
                    'action' => $tokens['action'],
                    'params' => $this->getActionParamsWithAttribute($action),
                    'authGuard' => $tokens['authGuard'],
                    'module' => $tokens['module'],
                ];
            }
            return;
        }

        // No HTTP verb attribute - use default based on method name
        $routeTemplate = $this->replaceTemplateTokens($template, $tokens);

        $this->routeTable[] = [
            'routeSegments' => count(explode('/', $routeTemplate)),
            'route' => $routeTemplate,
            'controller' => $controllerClassName,
            'httpMethod' => $this->getDefaultHttpMethod($tokens['action']),
            'action' => $tokens['action'],
            'params' => $this->getActionParamsWithAttribute($action),
            'authGuard' => $tokens['authGuard'],
            'module' => $tokens['module'],
        ];
    }

    /**
     * Extract area name from Area attribute.
     */
    private function getAreaAttribute(array $areaAttributes): ?string
    {
        if (count($areaAttributes) === 0) {
            return null;
        }
        return $areaAttributes[0]->newInstance()->name;
    }

    /**
     * Extract auth guards from Authorize attributes.
     */
    private function getAuthorizationAttribute(array $authorizationAttributes): ?array
    {
        if (count($authorizationAttributes) === 0) {
            return null;
        }

        $authGuards = [];
        foreach ($authorizationAttributes as $auth) {
            foreach ($auth->newInstance()->authGuards as $guard) {
                $authGuards[] = $guard;
            }
        }
        return $authGuards;
    }

    /**
     * Extract HTTP verb information from method.
     */
    private function getHttpVerbsFromMethod(ReflectionMethod $action): array
    {
        $verbs = [];

        foreach ($this->httpVerbs as $httpClass) {
            $attributes = $action->getAttributes($httpClass);

            foreach ($attributes as $attr) {
                $instance = $attr->newInstance();
                $reflection = new ReflectionClass($instance);

                $verbData = [];
                foreach ($reflection->getProperties() as $prop) {
                    $verbData[$prop->getName()] = $instance->{$prop->getName()};
                }
                $verbs[] = $verbData;
            }
        }

        return $verbs;
    }

    /**
     * Get action parameter metadata with binding sources.
     */
    private function getActionParamsWithAttribute(ReflectionMethod $action): array
    {
        $params = [];

        foreach ($action->getParameters() as $parameter) {
            $paramData = [
                'param' => $parameter,
                'bindingSource' => 'FromRoute' // Default
            ];

            foreach ($this->bindingSourceAttributes as $name => $class) {
                if (count($parameter->getAttributes($class)) > 0) {
                    $paramData['bindingSource'] = $name;
                    break;
                }
            }

            $params[] = $paramData;
        }

        return $params;
    }

    /**
     * Replace template tokens with actual values.
     */
    private function replaceTemplateTokens(string $template, array $tokens): string
    {
        foreach ($this->reservedRoutingNames as $tokenKey) {
            if (isset($tokens[$tokenKey]) && $tokens[$tokenKey] !== null) {
                $template = str_replace('[' . $tokenKey . ']', $tokens[$tokenKey], $template);
            }
        }
        return '/' . trim(strtolower($template), '/');
    }

    /**
     * Get default HTTP method based on action name.
     */
    private function getDefaultHttpMethod(string $actionName): string
    {
        return match ($actionName) {
            'httpGet' => 'get',
            'httpPost' => 'post',
            'httpPut' => 'put',
            'httpDelete' => 'delete',
            'httpPatch' => 'patch',
            'httpHead' => 'head',
            default => 'all'
        };
    }
}
