<?php

declare(strict_types=1);

namespace Spatial\Core;

/**
 * Route Table Renderer
 * 
 * Generates HTML documentation for the route table.
 * Provides API documentation at /api-docs endpoint.
 * 
 * @package Spatial\Core
 */
class RouteTableRenderer
{
    /**
     * Render route table as HTML documentation.
     * 
     * @param array $routeTable The route table to render
     * @return string HTML documentation
     */
    public function render(array $routeTable): string
    {
        $html = $this->getStyles();
        $html .= '<h2>API Documentation</h2><div>';

        // Group by modules and controllers
        $modules = $this->groupRoutes($routeTable);

        foreach ($modules as $moduleName => $controllers) {
            $html .= $this->renderModule($moduleName, $controllers);
        }

        $html .= '</div>';
        $html .= $this->getScript();

        return $html;
    }

    /**
     * Render route table as plain text (for CLI).
     * 
     * @param array $routeTable The route table to render
     * @return string Plain text documentation
     */
    public function renderText(array $routeTable): string
    {
        $output = "API Routes\n";
        $output .= str_repeat('=', 60) . "\n\n";

        $modules = $this->groupRoutes($routeTable);

        foreach ($modules as $moduleName => $controllers) {
            $moduleShort = $this->getShortName($moduleName);
            $output .= "{$moduleShort}\n";
            $output .= str_repeat('-', 40) . "\n";

            foreach ($controllers as $controllerName => $actions) {
                $controllerShort = $this->getShortName($controllerName);
                $output .= "  {$controllerShort}\n";

                foreach ($actions as $action) {
                    $method = strtoupper($action['httpMethod']);
                    $route = $action['route'];
                    $actionName = $action['action'];
                    $output .= "    [{$method}] {$route} -> {$actionName}()\n";
                }
            }
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Group routes by module and controller.
     */
    private function groupRoutes(array $routeTable): array
    {
        $modules = [];

        foreach ($routeTable as $route) {
            $module = $route['module'] ?? 'default';
            $controller = $route['controller'] ?? 'Unknown';
            $modules[$module][$controller][] = $route;
        }

        return $modules;
    }

    /**
     * Get short name from fully qualified class name.
     */
    private function getShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    /**
     * Render a single module section.
     */
    private function renderModule(string $moduleName, array $controllers): string
    {
        $moduleShort = $this->getShortName($moduleName);
        $moduleId = htmlspecialchars($moduleName);

        $html = "<div>";
        $html .= "<h3 onclick=\"toggleSection('{$moduleId}')\">{$moduleShort}</h3>";
        $html .= "<div id=\"{$moduleId}\" style=\"display: block; margin-left: 20px;\">";

        foreach ($controllers as $controllerName => $actions) {
            $html .= $this->renderController($moduleName, $controllerName, $actions);
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render a single controller section.
     */
    private function renderController(string $moduleName, string $controllerName, array $actions): string
    {
        $controllerShort = $this->getShortName($controllerName);
        $controllerId = htmlspecialchars($moduleName . '_' . $controllerName);

        $html = "<h4 onclick=\"toggleSection('{$controllerId}')\">📋 {$controllerShort}</h4>";
        $html .= "<div id=\"{$controllerId}\" style=\"display: block; margin-left: 20px;\">";
        $html .= '<table>';
        $html .= '<thead><tr><th>Action</th><th>Route</th><th>Params</th><th>Method</th><th>Auth</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($actions as $action) {
            $html .= $this->renderAction($action);
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Render a single action row.
     */
    private function renderAction(array $action): string
    {
        $actionName = htmlspecialchars($action['action']);
        $route = htmlspecialchars($action['route']);
        $method = strtoupper(htmlspecialchars($action['httpMethod']));
        $auth = htmlspecialchars(json_encode($action['authGuard'] ?? []));

        $params = '';
        if (!empty($action['params'])) {
            foreach ($action['params'] as $param) {
                $paramInfo = $param['param'];
                $binding = $param['bindingSource'] ?? 'FromRoute';

                if ($paramInfo instanceof \ReflectionParameter) {
                    $name = $paramInfo->getName();
                    $type = $paramInfo->getType()?->getName() ?? 'mixed';
                } else {
                    $name = $paramInfo['name'] ?? '?';
                    $type = $paramInfo['type'] ?? 'mixed';
                }

                $params .= "<pre style=\"margin:0\"><small>#[{$binding}]</small> ";
                $params .= "<span style=\"background:white\">{$name} : <span style=\"opacity:.5\">{$type}</span></span></pre>";
            }
        }

        return "<tr><td>{$actionName}</td><td>{$route}</td><td>{$params}</td><td>{$method}</td><td>{$auth}</td></tr>";
    }

    /**
     * Get CSS styles for HTML output.
     */
    private function getStyles(): string
    {
        return <<<'CSS'
<style>
    h2 { font-family: Arial, sans-serif; text-align: center; color: #333; margin-bottom: 20px; }
    h3, h4 { font-family: Arial, sans-serif; color: #2c3e50; margin-top: 20px; margin-bottom: 5px; cursor: pointer; }
    h3 { text-align: center; }
    h4 { text-align: left; }
    div { font-family: Arial, sans-serif; color: #555; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; font-family: Arial, sans-serif; font-size: 14px; color: #333; background-color: #f9f9f9; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    thead { background-color: #e8f5ff; text-align: left; }
    th, td { padding: 8px; border: 1px solid #ddd; }
    th { background-color: #dfefff; }
    tr:nth-child(even) { background-color: #f2f2f2; }
</style>
CSS;
    }

    /**
     * Get JavaScript for HTML output.
     */
    private function getScript(): string
    {
        return <<<'JS'
<script>
    function toggleSection(id) {
        var section = document.getElementById(id);
        section.style.display = section.style.display === "none" ? "block" : "none";
    }
</script>
JS;
    }
}
