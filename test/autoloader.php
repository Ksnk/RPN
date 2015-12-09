<?php

/**
 * Class Autoloader. PSR-0. Can't you head about PSR-0? Now you can see.
 */
class Autoloader
{
    var $dir = array();

    static function register($dir)
    {
        static $loader;
        if (empty($loader)) {
            $loader = new self();
            if (PHP_VERSION < 50300) {
                spl_autoload_register(array($loader, '__invoke'));
            } else {
                spl_autoload_register($loader);
            }
        }

        if (!is_array($dir)) $dir = array($dir);
        foreach ($dir as $d) {
            $loader->dir = array_merge($loader->dir, explode(';', $d));
        }

    }

    public function __invoke($classname)
    {
        // echo($classname.' '.getcwd().' '.json_encode($this->dir)."\n");
        foreach ($this->dir as $d) {
            $filename = $d . '/' . str_replace('\\', '/', $classname) . '.php';
            if (!file_exists($filename)) {
                continue;
            }
            require_once($filename);
        }
        return true;
    }
}

if (PHP_VERSION < 50300)
    Autoloader::register(dirname(__FILE__) . '\..;' . dirname(__FILE__) . '\..\samples\twig');
else
    Autoloader::register(__DIR__ . '\..;' . __DIR__ . '\..\samples\twig');