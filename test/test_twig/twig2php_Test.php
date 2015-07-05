<?php

/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 13:53
 *
 * @method assertEquals - злой phpstorm не видит моего PHPUnit и обижаеццо
 */
class twig2php_Test extends PHPUnit_Framework_TestCase
{

    function testCreateAndRun()
    {
        $r= new twig2php_class();
        //$filename = "php://memory";

        $fp = fopen("php://memory", "w+b");
        fwrite($fp, str_repeat('Привет мир!',256));//1024));
        rewind($fp);
        $r->handler=$fp;
        $this->assertEquals($r->tplcalc(), '
        Hello, world!
        ');
        fclose($fp);
    }

}