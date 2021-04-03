<?php

declare(strict_types=1);

namespace Spatial\Router;

use JetBrains\PhpStorm\Pure;

/**
 * ActivatedRoute Class exists in the Spatial\Router namespace
 * This class initialized $_GET global.
 * Strings sent through the GET global are passed through
 * the html-special-char() function to remove any tags
 *
 * @category Router
 */
class ActivatedRoute
{
    protected array $params = [];


    public function setParams(array $queryParams): void
    {
//        load params
        foreach ($queryParams as $key => $value) {
            // clean it of any html params
            // for $_GET only: Remove an html tags and quotes
            if (!is_iterable($value)) {
                $this->params[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $this->params[$key] = $value;
            }
        }
    }

    public function __isset(string $name): bool
    {
        return $this->params[$name];
    }

    public function __set(string $name, $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * Method to get local variables of this class
     * @param string $param
     * @return mixed
     */
    #[Pure] public function __get(string $param): mixed
    {
        // Check if the param exists
        return $this->params[$param] ?? null;
    }
}
