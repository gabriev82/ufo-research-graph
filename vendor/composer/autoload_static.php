<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite32a1b075878db587d14e27dd62b23c5
{
    public static $files = array (
        '6e3fae29631ef280660b3cdad06f25a8' => __DIR__ . '/..' . '/symfony/deprecation-contracts/function.php',
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Ctype\\' => 23,
            'Symfony\\Component\\Yaml\\' => 23,
        ),
        'G' => 
        array (
            'Graphp\\GraphViz\\' => 16,
            'Graphp\\Algorithms\\' => 18,
        ),
        'F' => 
        array (
            'Fhaculty\\Graph\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
        'Symfony\\Component\\Yaml\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/yaml',
        ),
        'Graphp\\GraphViz\\' => 
        array (
            0 => __DIR__ . '/..' . '/graphp/graphviz/src',
        ),
        'Graphp\\Algorithms\\' => 
        array (
            0 => __DIR__ . '/..' . '/graphp/algorithms/src',
        ),
        'Fhaculty\\Graph\\' => 
        array (
            0 => __DIR__ . '/..' . '/clue/graph/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite32a1b075878db587d14e27dd62b23c5::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite32a1b075878db587d14e27dd62b23c5::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}