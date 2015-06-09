<?php

/**
 * Created by PhpStorm.
 * User: ������
 * Date: 08.06.15
 * Time: 13:53
 */
class revpolnot_classTest extends PHPUnit_Framework_TestCase
{

    /**
     * �������� ������������� ������ ���������� +
     * �������� ����������. �� �������� �� ����� ����� ����������?
     */
    function testBuildingSyntaxTree()
    {
        $r = new revpolnot_class();
        $r->option(array(
            // 'flags' => 12,
            'operation' => ['AND' => 3, 'OR' => 3, 'NOT' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['NOT' => 3],
        ));
        foreach ([
                     '(172* ) OR not(501* OR 128) AND NOT 201*' =>
                         '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"data":"128"},{"op":"OR"},{"op":"NOT","unop":true},{"op":"OR"},{"data":"201"},{"op":"*","unop":true},{"op":"NOT","unop":true},{"op":"AND"}]',
                     '128 (501* not 503*)' => '[{"data":"128"},{"data":"501"},{"op":"*","unop":true},{"data":"503"},{"op":"*","unop":true},{"op":"NOT"},{"op":"_EMPTY_"}]',
                     '172* or 501* and not 128' => '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"OR"},{"data":"128"},{"op":"NOT","unop":true},{"op":"AND"}]',
                     '172* 501* not 128' => '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"128"},{"op":"NOT"}]',
                     '(((((((((172* 501*))))))))) not 128' => '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"128"},{"op":"NOT"}]',
                     '((172* 501*)and(173* 234*) or(4567* 345*) not 345) not 128' => '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"173"},{"op":"*","unop":true},{"data":"234"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"op":"AND"},{"data":"4567"},{"op":"*","unop":true},{"data":"345"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"op":"OR"},{"data":"345"},{"op":"NOT"},{"data":"128"},{"op":"NOT"}]',
                     '((((((172* 501*)and 173*) 234*) or 4567*) 345*) not 345) not 128' => '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"173"},{"op":"*","unop":true},{"op":"AND"},{"data":"234"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"4567"},{"op":"*","unop":true},{"op":"OR"},{"data":"345"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"345"},{"op":"NOT"},{"data":"128"},{"op":"NOT"}]',
                     '(172* (501* and (173* (234* or (4567* (345* and (345 not 128)))))))' => '[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"data":"173"},{"op":"*","unop":true},{"data":"234"},{"op":"*","unop":true},{"data":"4567"},{"op":"*","unop":true},{"data":"345"},{"op":"*","unop":true},{"data":"345"},{"data":"128"},{"op":"NOT"},{"op":"AND"},{"op":"_EMPTY_"},{"op":"OR"},{"op":"_EMPTY_"},{"op":"AND"},{"op":"_EMPTY_"}]',
                 ] as $k => $v) {
            $this->assertEquals('[]', json_encode($r->error()));
            $this->assertEquals($k . "\n" . $v, $k . "\n" . json_encode($r->ev($k,false)));
        }
    }

    /**
     * �������� ����������
     */
    function testClassCalculation()
    {
        $r = new revpolnot_class();
        $r->option(array(
            // 'flags' => 12,
            'operation' => ['AND' => 3, 'OR' => 3, 'NOT' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['NOT' => 3],

            'evaluateTag' => [$this, '_celOp'],
            'executeOp' => [$this, '_celOpr']
        ));
        foreach ([
                     '(172* ) OR (501* OR 128) AND NOT 201*' => true,
                     '(172* ) OR not(501* OR 128) AND NOT 201*' => false,
                 ] as $k => $v) {
            $this->assertEquals('[]', json_encode($r->error()));
            $this->assertEquals($k . "\n" . json_encode($v), $k . "\n" . json_encode($r->ev($k)));
        }
    }

    /**
     * �������� ������� ��� �������� ����������� ������
     * ���������� ���������� ���� - ��������� �������� �� > c ����������
     * @param $op
     * @return bool
     */
    function _celOp($op)
    {
        $result = false;
        if (is_bool($op)) $result = $op;
        else {
            if (isset($op['data'])) {
                $result = $op['data'] > 202;
            }
            if (!empty($op['not'])) {
                $result = !$result;
            }
        }
        //if (\cel_class::$debug)
        //    echo "\n" . 'eval:' . json_encode($op) . '=' . ($result ? 'true' : 'false') . '<br>';

        return $result;
    }

    function _celOpr($op, $_1, $_2, $evaluate, $unop = false)
    {
        $result = false;
        if ($op == '*') {
            $result = call_user_func($evaluate, $_2);
        } else if ($op == 'OR' || $op == '_EMPTY_') {
            if ($_1 === true || $_2 === true) {
                $result = true;
            } else {
                if (!call_user_func($evaluate, $_1))
                    $result = call_user_func($evaluate, $_2);
                else
                    $result = true;
            }
        } else if ($op == 'AND') {
            if ($_1 === false || $_2 === false) {
                $result = false;
            } else {
                if (call_user_func($evaluate, $_1))
                    $result = call_user_func($evaluate, $_2);
                else
                    $result = false;
            }
        } else if ($op == 'NOT' && $unop) {
            $result = !call_user_func($evaluate, $_2);
        } else if ($op == 'NOT') { // ������ AND NOT
            if ($_1 === false || $_2 === true)
                $result = false;
            else
                $result = call_user_func($evaluate, $_1) && !call_user_func($evaluate, $_2);
        }
        return $result;
    }

    /**
     * �������� ��������� ������������
     */
    function testNumberCalculation()
    {
        $r = new revpolnot_class();
        $r->option(array(
            // 'flags' => 12,
            'operation' => ['+' => 4, '-' => 4, '*' => 5, '/' => 5,],
            'suffix' => ['++' => 1],
            'unop' => ['-' => 1],
            'tagreg' => '\b(\d+)\b',

            'evaluateTag' => function ($op) use ($r) {
                $r->log('eval:' . json_encode($op));
                if (!is_array($op) || !isset($op['data']))
                    $result = $op;
                else {
                    $result = 0 + $op['data']; // ����� �������������� � �����
                }
                return $result;
            },
            'executeOp' => function ($op, $_1, $_2, $evaluate, $unop = false) use ($r) {
                $r->log('oper:' . json_encode($_1) . ' ' . $op . ' ' . json_encode($_2));
                if ($op == '+') {
                    return call_user_func($evaluate, $_1) + call_user_func($evaluate, $_2);
                } else if ($op == '++') {
                    return call_user_func($evaluate, $_2) + 1;
                } else if ($op == '-' && $unop) {
                    return -call_user_func($evaluate, $_2);
                } else if ($op == '-') {
                    return call_user_func($evaluate, $_1) - call_user_func($evaluate, $_2);
                } else if ($op == '*') {
                    return call_user_func($evaluate, $_1) * call_user_func($evaluate, $_2);
                } else if ($op == '/') {
                    return call_user_func($evaluate, $_1) / call_user_func($evaluate, $_2);
                } else {
                    $r->error('unknown operation '.$op);
                }
                return 0;
            }
        ));
        foreach ([
                     '1' => 1,
                     '-1' => -1,
                     '(-3*-----4)*4++/5' => 12,
                     '1+2+3+4+5' => 15,
                 ] as $k => $v) {
            $result = $r->ev($k);
            $this->assertEquals('[]', json_encode($r->error()));
            $this->assertEquals($k . "\n" . json_encode($v), $k . "\n" . json_encode($result));
        }
    }

}
 