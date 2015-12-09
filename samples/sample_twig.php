<?php
/**
 * Created by PhpStorm.
 * User: Аня
 * Date: 22.07.15
 * Time: 13:16
 */
ini_set('display_errors',1);
error_reporting(E_ALL & E_NOTICE);

require '../test/autoloader.php';
Autoloader::register(__DIR__ . '\twig');

template_compiler::checktpl(array(
    'TEMPLATE_PATH'=>__DIR__ .'/twig/templates',
    'PHP_PATH'=>__DIR__ .'/twig/templates',
));
