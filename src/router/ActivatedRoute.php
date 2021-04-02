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
    protected static array $params = [];


    public static function setParams(array $queryParams)
    {
//        reset param
        self::$params = [];
        $tmpParams =[];
//        load params
        foreach ($queryParams as $key => $value) {
            // clean it of any html params
            // for $_GET only: Remove an html tags and quotes
            if (!is_iterable($value)) {
                $tmpParams[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $tmpParams[$key] = $value;
            }

        }
        self::$params = $tmpParams;
//        print_r(self::$params);
    }


//    /**
//     * Setter Method for the class
//     *
//     * @param string $key
//     * @param $value
//     * @return void
//     */
//    public function set(string $key, mixed $value): void
//    {
//        self::params[$key] = $value;
//    }

    /**
     * @return array
     */
    public static function get(): array
    {
        return self::$params;
    }

    /**
     * Method to get local variables of this class
     * @param string $param
     * @param array $args
     * @return mixed|null
     */
    public static function getParam(string $param, array $args = []): mixed
    {
        // Check if the param exists
        if (!array_key_exists($param, self::params)) {
            return null;
            // throw new \Exception("The REQUEST Parameter: $param does not exist.");
        }
//        if (!empty($args)) {
//            return self::param[$param]($args);
//        }
        // Return the existing Param
        return self::$params[$param];
    }
}
