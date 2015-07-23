<?php

/**
 * тестовый наследник - для отладки шаблонов. Новая конструкция сначала отлаживается здесь, потом
 * перемещается в Test
 * Class tpl_test
 */
if(!class_exists('tpl_test')){
class tpl_test extends tpl_base
{

    function __construct()
    {

    }

    function _($par = 0)
    {
        return $this->_test($par) . ' Calling parent_ method';
    }

    function _test(&$par)
    {
        return 'Calling parent test! Ok! ';
    }
}
    class engine
    {
        function export($class, $method, $par1 = null, $par2 = null, $par3 = null)
        {
            return sprintf('calling %s::%s(%s)', $class, $method, array_diff(array($par1, $par2, $par3), array(null)));
        }

        function test($a, $b, $c)
        {
            return $a . '+' . $b . '+' . $c;
        }
    }

    $GLOBALS['engine'] = new engine();
}

/**
 *
 */
class twig2php_DebugTest extends PHPUnit_Framework_TestCase
{
    var $currentrpn=null;

    function compress($s)
    {
        return preg_replace('/\s+/s', ' ', $s);
    }

    function compilerX($src,$par=array(),$method='_'){
        static $cnt=0,$r=null;
        if(is_null($r))$r= new twig2php_class();
        $cnt++;
        //$r= new twig2php_class();
        $fp = fopen("php://memory", "w+b");
        fwrite($fp, $src);//1024));
        rewind($fp);
        $r->handler=$fp;
        $res=''.$r->ev(array('tplcalc','compiler','test'.$cnt));
        $errors=$r->error();
        if($errors) print_r($errors);
        eval('?>'. $res);
        $classname='tpl_test'.$cnt;
        //echo $data;
        fclose($fp);
        if(class_exists($classname)) {
            $base= new $classname();
            $x=$base->$method($par);
            return $x;
        } else
            return '';
    }

    /**
     * условное предложение, вырезка из массива
     */



    /**
     * Тестовая функция для проверки логического модуля
     * Реализация исчисления тега - сравнение значения на > c константой
     * @param $op
     * @return bool
     */
    function _celOp($op)
    {
        if (!is_a($op,'operand'))
            $result = $op;
        else {
            $result = $op->val > 202;
        }
        //if (\cel_class::$debug)
        //    echo "\n" . 'eval:' . json_encode($op) . '=' . ($result ? 'true' : 'false') . '<br>';

        return $result;
    }

    function _celOpr($op, $_1, $_2, $evaluate)
    {
        $result = false;
        if ($op->val == '*') {
            $result = call_user_func($evaluate, $_2);
        } else if ($op->val == 'OR' || $op->val == '_EMPTY_') {
            if ($_1 === true || $_2 === true) {
                $result = true;
            } else {
                if (!call_user_func($evaluate, $_1))
                    $result = call_user_func($evaluate, $_2);
                else
                    $result = true;
            }
        } else if ($op->val == 'AND') {
            if ($_1 === false || $_2 === false) {
                $result = false;
            } else {
                if (call_user_func($evaluate, $_1))
                    $result = call_user_func($evaluate, $_2);
                else
                    $result = false;
            }
        } else if ($op->val == 'NOT' && $op->unop) {
            $result = !call_user_func($evaluate, $_2);
        } else if ($op->val == 'NOT') { // делаем AND NOT
            if ($_1 === false || $_2 === true)
                $result = false;
            else
                $result = call_user_func($evaluate, $_1) && !call_user_func($evaluate, $_2);
        }
        return $result;
    }

}

