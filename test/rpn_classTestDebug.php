<?php

/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 13:53
 *
 */
class rpn_classTestDebug extends PHPUnit_Framework_TestCase
{

    /** @var rpn_class  */
    var $current_rpn = null;

    /**
     * проверка строительства дерева синтаксиса +
     * массовых вычислений. не остается ли грязи между итерациями?
     */
    /**
     * проверка результата
     */
    function testClassCalculation()
    {
        $r = new rpn_class();
        $r->option(array(
            // 'flags' => 12,
            'operation' => ['and' => 3, 'or' => 3, 'not' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['not' => 3],

            'evaluateTag' => [$this, '_celOp'],
            'executeOp' => [$this, '_celOpr']
        ));
        foreach ([
                     '(172* ) OR (501* OR 128) AND NOT 201*' => true,
                     '(172* ) OR not(501* OR 128) AND NOT 201*' => false,
                 ] as $k => $v) {
            $result=$r->ev($k);
            $this->assertEquals('[]', json_encode($r->error()));
            $this->assertEquals($k . "\n" . json_encode($v), $k . "\n" . json_encode($result));
        }
    }

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
        } else if ($op->val == 'or' || $op->val == '_EMPTY_') {
            if ($_1 === true || $_2 === true) {
                $result = true;
            } else {
                if (!call_user_func($evaluate, $_1))
                    $result = call_user_func($evaluate, $_2);
                else
                    $result = true;
            }
        } else if ($op->val == 'and') {
            if ($_1 === false || $_2 === false) {
                $result = false;
            } else {
                if (call_user_func($evaluate, $_1))
                    $result = call_user_func($evaluate, $_2);
                else
                    $result = false;
            }
        } else if ($op->val == 'not' && $op->unop) {
            $result = !call_user_func($evaluate, $_2);
        } else if ($op->val == 'not') { // делаем AND NOT
            if ($_1 === false || $_2 === true)
                $result = false;
            else
                $result = call_user_func($evaluate, $_1) && !call_user_func($evaluate, $_2);
        }
        return $result;
    }


}
 