<?php
/**
 * скомпилировать compiler.twig 3 раза и убедится, что осталcя еще разум добро и кусочек вечности
 */

require '../../test/autoloader.php';

$orig = __DIR__ . '/templates/compiler.twig';
$dest = __DIR__ . '/templates/tpl_compiler.php';

$errors = array();

/**
 * компилировать шаблон $orig, используя tpl - compiler
 * получившийся tpl - класс назвать compiler1
 * результат трансляции выдать в переменную $dst, или eval
 *
 * @param $compiler
 * @param $compiler1
 * @param null $dst
 * @return bool
 */
$doit = function ($compiler, $compiler1, &$dst = null) use ($errors, $orig) {
    $r = new twig2php_class();
    $fp = fopen($orig, "r");
    $r->handler = $fp;
    $res = '' . $r->ev(array('tplcalc', $compiler, $compiler1));
    fclose($fp);
    $errors = $r->error();
    if ($errors) return false;
    if (is_null($dst))
        eval('?>' . $res);
    else
        $dst = $res;
    return true;
};

do {
    $res1 = '';
    $res2 = '';

    if (!$doit('compiler', 'compilerX')) break;

    if (!$doit('compilerX', 'compiler1')) break;

    if (!$doit('compiler1', 'compiler', $res1)) break;

    if (!$doit('compiler1', 'compiler2')) break;

    if (!$doit('compiler2', 'compiler', $res2)) break;

    if ($res1 != $res2) {
        echo 'generated codes not identical, sorry!';
    }

    file_put_contents($dest, $res1);

} while (false);

if ($errors) print_r($errors);