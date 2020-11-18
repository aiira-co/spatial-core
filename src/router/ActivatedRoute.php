<?php

declare(strict_types=1);

namespace Spatial\Router;

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

    /**
     * Converts $_GET global  members into variables of this class
     */
    public function __construct()
    {
        foreach ($_REQUEST as $key => $value) {
            // clean it of any html params
            // for $_GET only: Remove an html tags and quotes
            if (!is_iterable($value)) {
                $this->params[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $this->params[$key] = $value;
            }
        }
    }

    /**
     * Getter Method for the class
     *
     * @param string $key
     * @return void
     */
    public function __get(string $key)
    {
        return $this->_getParam($key);
    }

    /**
     * Setter Method for the class
     *
     * @param string $key
     * @param $value
     * @return void
     */
    public function __set(string $key, $value)
    {
        $this->params[$key] = $value;
    }

    /**
     * Method to get local variables of this class
     * @param string $param
     * @param array $args
     * @return mixed|null
     */
    private function _getParam(string $param, array $args = [])
    {
        // Check if the param exists
        if (!array_key_exists($param, $this->params)) {
            return null;
            // throw new \Exception("The REQUEST Parameter: $param does not exist.");
        }
//        if (!empty($args)) {
//            return $this->$param[$param]($args);
//        }
        // Return the existing Param
        return $this->params[$param];
    }
}
