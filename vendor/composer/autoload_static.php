<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit18c6cd601cfd699c21671485f56b85b8
{
    public static $files = array (
        '7b11c4dc42b3b3023073cb14e519683c' => __DIR__ . '/..' . '/ralouphie/getallheaders/src/getallheaders.php',
        'a0edc8309cc5e1d60e3047b5df6b7052' => __DIR__ . '/..' . '/guzzlehttp/psr7/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Spatial\\Swoole\\' => 15,
            'Spatial\\Router\\' => 15,
            'Spatial\\Psr7\\' => 13,
            'Spatial\\Infrastructure\\' => 23,
            'Spatial\\Core\\' => 13,
            'Spatial\\Common\\' => 15,
            'Spatial\\Api\\' => 12,
        ),
        'P' => 
        array (
            'Psr\\Http\\Server\\' => 16,
            'Psr\\Http\\Message\\' => 17,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Spatial\\Swoole\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/swoole',
        ),
        'Spatial\\Router\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/router',
        ),
        'Spatial\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/spatial/psr7/src',
        ),
        'Spatial\\Infrastructure\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/infrastructure',
        ),
        'Spatial\\Core\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/core',
        ),
        'Spatial\\Common\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src/common',
        ),
        'Spatial\\Api\\' => 
        array (
            0 => __DIR__ . '/../..' . '/test',
        ),
        'Psr\\Http\\Server\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-server-handler/src',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit18c6cd601cfd699c21671485f56b85b8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit18c6cd601cfd699c21671485f56b85b8::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit18c6cd601cfd699c21671485f56b85b8::$classMap;

        }, null, ClassLoader::class);
    }
}
