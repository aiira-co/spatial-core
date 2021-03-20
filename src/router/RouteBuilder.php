<?php

declare(strict_types=1);

namespace Spatial\Router;


use Spatial\Router\Interfaces\RouteBuilderInterface;
use Spatial\Router\Trait\SecurityTrait;

class RouteBuilder implements RouteBuilderInterface
{
    use SecurityTrait;

    public string $name;
    public string $pattern;
    public object $defaults;

    public bool $useAttributeRouting;

    public function mapDefaultControllerRoute(): self
    {
        $this->name = 'default';
        $this->pattern = '{controller=Home}/{action=Index}/{id?}';
        return clone $this;
    }

    public function mapControllers(): self
    {
        $this->useAttributeRouting = true;
        return clone $this;
    }

    public function mapControllerRoute(string $name, string $pattern, ?object $defaults = null): self
    {
        $this->name = trim($name);
        $this->pattern = urlencode(trim($pattern, '/'));


        $this->defaults = $defaults ??
            new class {
                public string $content;

                public function __construct()
                {
                    $this->content = file_get_contents('php://input');
                }
            };


        return clone $this;
    }

}
