<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit81e99f1dc566eef968328061a70cec66
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit81e99f1dc566eef968328061a70cec66::$classMap;

        }, null, ClassLoader::class);
    }
}
