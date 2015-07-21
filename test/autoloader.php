<?php

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
        // echo($classname.' '.getcwd());
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

Autoloader::register(__DIR__ . '\..;' . __DIR__ . '\..\samples\twig');