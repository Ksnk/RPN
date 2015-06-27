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
        fwrite($fp, '
        Hello, world!
        ');
        rewind($fp);

        $this->assertEquals($r->evstream($fp), '
        Hello, world!
        ');
        fclose($fp);
    }

}