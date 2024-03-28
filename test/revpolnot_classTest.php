<?php
include_once '../vendor/autoload.php';

use PHPUnit\Framework\TestCase,
    Ksnk\rpn\rpn_class;

/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 13:53
 *
 */
class revpolnot_classTest extends TestCase
{

    /** @var rpn_class */
    var $current_rpn = null;

    /**
     * Проверка строительства дерева синтаксиса +
     * массовых вычислений. Не остается ли грязи между итерациями?
     */
    /*
    function testBuildingSyntaxTree()
    {
        $r = new rpn_class();
        $r->option(array(
            'flags' => rpn_class::EMPTY_FUNCTION_ALLOWED
            //      | 12
        ,
            'operation' => ['AND' => 3, 'OR' => 3, 'NOT' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['NOT' => 3],
        ));
        foreach ([
                     '(172* ) OR not(501* OR 128) AND NOT 201*' =>
                         '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"data":"128","type":12},{"op":"OR"},{"op":"NOT","unop":1},{"op":"OR"},{"data":"201","type":12},{"op":"*","unop":2},{"op":"NOT","unop":1},{"op":"AND"}]',
                     '128 (501* not 503*)' => '[{"data":"128","type":12},{"data":"501","type":12},{"op":"*","unop":2},{"data":"503","type":12},{"op":"*","unop":2},{"op":"NOT"},{"op":"_EMPTY_"}]',
                     '172* or 501* and not 128' => '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"OR"},{"data":"128","type":12},{"op":"NOT","unop":1},{"op":"AND"}]',
                     '172* 501* not 128' => '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"128","type":12},{"op":"NOT"}]',
                     '(((((((((172* 501*))))))))) not 128' => '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"128","type":12},{"op":"NOT"}]',
                     '((172* 501*)and(173* 234*) or(4567* 345*) not 345) not 128' => '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"173","type":12},{"op":"*","unop":2},{"data":"234","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"op":"AND"},{"data":"4567","type":12},{"op":"*","unop":2},{"data":"345","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"op":"OR"},{"data":"345","type":12},{"op":"NOT"},{"data":"128","type":12},{"op":"NOT"}]',
                     '((((((172* 501*)and 173*) 234*) or 4567*) 345*) not 345) not 128' => '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"173","type":12},{"op":"*","unop":2},{"op":"AND"},{"data":"234","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"4567","type":12},{"op":"*","unop":2},{"op":"OR"},{"data":"345","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"345","type":12},{"op":"NOT"},{"data":"128","type":12},{"op":"NOT"}]',
                     '(172* (501* and (173* (234* or (4567* (345* and (345 not 128)))))))' => '[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"data":"173","type":12},{"op":"*","unop":2},{"data":"234","type":12},{"op":"*","unop":2},{"data":"4567","type":12},{"op":"*","unop":2},{"data":"345","type":12},{"op":"*","unop":2},{"data":"345","type":12},{"data":"128","type":12},{"op":"NOT"},{"op":"AND"},{"op":"_EMPTY_"},{"op":"OR"},{"op":"_EMPTY_"},{"op":"AND"},{"op":"_EMPTY_"}]',
                 ] as $k => $v) {
            $this->assertEquals($k . "\n" .'[]', $k . "\n" .json_encode($r->error()));
            $this->assertEquals($k . "\n" . $v, $k . "\n" . json_encode($r->ev($k, false)));
        }
    }
/**/

    /**
     * Проверка результата
     */
    function testClassCalculation()
    {
        $r = new rpn_class();
        $r->option(array(
            'flags' => 0/**/
                // + rpn_class::SHOW_DEBUG + rpn_class::SHOW_ERROR
                //    + rpn_class::ALLOW_REAL
                //    + rpn_class::ALLOW_STRINGS + rpn_class::ALLOW_ID
                //    + rpn_class::ALLOW_COMMA
                + rpn_class::CASE_SENCITIVE
        ,
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
            $result = $r->ev($k);
            $this->assertEquals('[]', json_encode($r->error()));
            $this->assertEquals($k . "\n" . json_encode($v), $k . "\n" . json_encode($result));
        }
    }

    /**
     * Проверка числового калькулятора
     */
    function testNumberCalculation()
    {
        $r = new rpn_class();
        $this->current_rpn = $r;
        $r->option(array(
            'flags' => 0/**/
                // + rpn_class::SHOW_DEBUG + rpn_class::SHOW_ERROR
                + rpn_class::ALLOW_REAL
            //+ rpn_class::ALLOW_STRINGS + rpn_class::ALLOW_ID
            //+ rpn_class::ALLOW_COMMA
        ,

            //'flags' => 12,
            'operation' => ['+' => 4, '-' => 4, '*' => 5, '/' => 5,],
            'suffix' => ['++' => 1],
            'unop' => ['-' => 1],
            'tagreg' => '\b\d+\b',
            'reserved_words' => ['pi' => 0, 'e' => 0,
                'floor' => 1,
                'pow' => 2,
                'summ' => -1,
            ],

            'evaluateTag' => [$this, '_calcTag'],
            'executeOp' => [$this, '_calcOp'],
        ));
        foreach ([
                     '(-3*-----4)*4++/5' => 12,
                     'e+summ(1,2,1,2,1,2,1,2)-pow(3,4)+floor(56/3)' => -48.28172, //181715,// -48.28171817154096'
                     '1+2+3+4+5' => 15,
                     '1' => 1,
                     '-1' => -1,

                 ] as $k => $v) {
            $result = $r->ev($k);
            if (is_float($result)) $result = round($result, 5);
            //echo $k, '=', $result, PHP_EOL;
            $this->assertEquals($k . "\n" . '[]', $k . "\n" . json_encode($r->error()));
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
        if (!is_object($op))
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

    /**/
    function _calcTag($op)
    {
        //$this->current_rpn->log('eval:' . json_encode($op));
        if (!is_object($op))
            $result = $op;
        else {
            $result = 0 + $op->val; // явное преобразование к числу
        }
        return $result;
    }

    function _calcOp($op, $_1, $_2, $evaluate)
    {
        if (0 == ($this->current_rpn->flags & rpn_class::SHOW_DEBUG)) $this->current_rpn->log('oper:' . json_encode($_1) . ' ' . $op . ' ' . json_encode($_2));
        if ($op == '+') {
            return call_user_func($evaluate, $_1) + call_user_func($evaluate, $_2);
        } else if ($op == '++') {
            return call_user_func($evaluate, $_2) + 1;
        } else if ($op == '-' && $op->unop) {
            return -call_user_func($evaluate, $_2);
        } else if ($op == '-') {
            return call_user_func($evaluate, $_1) - call_user_func($evaluate, $_2);
        } else if ($op == '*') {
            return call_user_func($evaluate, $_1) * call_user_func($evaluate, $_2);
        } else if ($op == '/') {
            return call_user_func($evaluate, $_1) / call_user_func($evaluate, $_2);
        } elseif ($op == 'pi') {
            return pi();
        } elseif ($op == 'e') {
            return M_E;
        } elseif ($op == 'pow') {
            return pow(call_user_func($evaluate, $_2[0]), call_user_func($evaluate, $_2[1]));
        } elseif ($op == 'floor') {
            return floor(call_user_func($evaluate, $_2[0]));
        } elseif ($op == 'summ') {
            $result = 0;
            foreach ($_2 as $x) {
                $result += call_user_func($evaluate, $x);
            }
            return $result;
        } else {
            $this->current_rpn->error('unknown operation ' . $op);
        }
        return 0;
    }

}
 