<?php

class Autoloader
{
    public function __construct($dir)
    {
        $this->dir=explode(';',$dir);
    }

    public function register()
    {
        \spl_autoload_register($this);
    }

    public function __invoke($classname)
    {
       // echo($classname.' '.getcwd());
        foreach($this->dir as $d){
            $filename = $d.'/'.\str_replace('\\', '/', $classname).'.php';
            if (!\file_exists($filename)) {
                continue;
            }
            require_once($filename);
        }
        return true;
    }
}

//$loader=new Autoloader('..;.'); //
$loader=new Autoloader(__DIR__.'\..');
$loader->register();