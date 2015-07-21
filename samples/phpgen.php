<?php
/**
 * php-script generator
 *
 */

/*
return $parser ->newOp2('- +',3)
    ->newOp2('* /',5)
    ->newOp2('|| && >> <<',4)
    ->newOp2('^',7,'pow(%s,%s)')
    ->newOp1('-','-(%s)')
    ->newOp1('tan abs sin cos')
    ->newOpr('pi','pi()')
    ->newOpr('x','$x')
    ->newOpr('time','$self->c_getTime()')
    ;*/

require '../test/autoloader.php';

// create php-string and create_function with it.
$r = new rpn_class();

set_error_handler(function ($c, $m /*, $f, $l*/) {
    throw new Exception($m, $c);
}, E_ALL & ~E_NOTICE);

$r->option(array(
    'flags'=>0//rpn_class::SHOW_DEBUG+rpn_class::SHOW_ERROR
            +rpn_class::ALLOW_STRINGS
,
    'operation' => ['+' => 4, '-' => 4, '*' => 5, '/' => 5, '^' => 7, '||' => 2, '&&' => 2, '>>' => 3, '<<' => 3],
    'suffix' => ['++' => 1],
    'unop' => ['++'=>1,'-' => 1, '+' => 1],
    'tagreg' => '\b(?:\d\w*(?:\.[\d]+)?(?:E[\+\-][\d]+)?)',
    'reserved_words' => ['PI' => 0, 'E' => 0,
        'TAN' => 1,
        'ABS' => 1,
        'SIN' => 1,
        'COS' => 1,
        'X' => 0,
        'TIME' => 0,
    ],

    /**
     * идеология выполнения -
     * @param operand|mixed $op
     */
    'evaluateTag' => function ($op) use ($r) {
            if(is_a($op,'operand')){
                if ($op->type == rpn_class::TYPE_STRING) {
                    $result = ''.$op->val;
                } elseif ($op->type == rpn_class::TYPE_XSTRING) {
                    $result = $op->val;
                } else {
                    $result = 0+$op->val;
                }
            } else {
                $result=$op;
            }
            $r->log('eval:' . json_encode($op));
            return $result;
        },
    'executeOp' => function ($op, $_1, $_2, $evaluate, $unop = false) use ($r) {
            $r->log('oper:' . json_encode($_1) . ' ' . $op . ' ' . json_encode($_2));

            if (!$op->unop && $op=='+' && $_1->type==rpn_class::TYPE_STRING) {
                return  new operand( sprintf("('%s' . '%s')", str_replace("'",'\\\'',call_user_func($evaluate, $_1)), str_replace("'",'\\\'',call_user_func($evaluate, $_2))),  rpn_class::TYPE_XSTRING);
            } elseif (!$op->unop>0 && in_array($op, ['+', '-', '*', '/', '||', '&&', '>>', '<<'])) {
                return  new operand(sprintf('(%s %s %s)', call_user_func($evaluate, $_1), $op, call_user_func($evaluate, $_2)), rpn_class::TYPE_XSTRING);
            } elseif (!$op->unop>0 && $op == '^') {
                return  new operand( sprintf('pow(%s,%s)', call_user_func($evaluate, $_1), call_user_func($evaluate, $_2)), rpn_class::TYPE_XSTRING);
            } elseif (1==$op->unop && $op == '++') {
                return  new operand(sprintf('++%s', call_user_func($evaluate, $_2)), rpn_class::TYPE_XSTRING);
            } elseif (2==$op->unop && $op == '++') {
                return  new operand( sprintf('%s++', call_user_func($evaluate, $_2)), rpn_class::TYPE_XSTRING);
            } elseif ($op->unop && $op == '-') {
                return  new operand( sprintf('(- %s)', call_user_func($evaluate, $_2)), rpn_class::TYPE_XSTRING);
            } elseif ($op->unop && $op == '+') {
                return  new operand( sprintf('(%s)', call_user_func($evaluate, $_2)), rpn_class::TYPE_XSTRING);
            } elseif ($op == 'PI') {
                return  new operand('M_PI', rpn_class::TYPE_XSTRING);
            } elseif ($op == 'E') {
                return  new operand( 'M_E', rpn_class::TYPE_XSTRING);
            } elseif ($op == 'TAN') {
                return  new operand( sprintf('tan(%s)', call_user_func($evaluate, $_2[0])), rpn_class::TYPE_XSTRING);
            } elseif ($op == 'ABS') {
                return  new operand(sprintf('abs(%s)', call_user_func($evaluate, $_2[0])), rpn_class::TYPE_XSTRING);
            } elseif ($op == 'SIN') {
                return new operand(sprintf('sin(%s)', call_user_func($evaluate, $_2[0])), rpn_class::TYPE_XSTRING);
            } elseif ($op == 'X') {
                return new operand('$x', rpn_class::TYPE_XSTRING);
            } elseif ($op == 'COS') {
                return new operand(sprintf('cos(%s)', call_user_func($evaluate, $_2[0])), rpn_class::TYPE_XSTRING);
            } elseif ($op == 'TIME') {
                return new operand('time()', rpn_class::TYPE_XSTRING);
            } else {
                $r->error('unknown operation ' . $op);
            }
            return 0;
        }
));

$x = 1;

foreach ([  //*
             '1' => ['result' =>1],
             '1+1' => ['result' =>2],
             '(1+1*2+1)+(1*2)' => ['result' =>3],
             '1+1*2' => ['result' =>3],
             '1*1+2' => ['result' =>3],
             '1*1+2^2' => ['result' =>5],
             'pi/2' => ['result' =>pi()/2],
             'sin(pi/2)' => ['result' =>1],
             'sinx(pi/2)' => ['error' => ["[0:4] "]],
            // 'sin(pii/x)' => ['error' => null],
             'sin(pi/x' => ['error' => null],
             'x+1' => ['x'=>1,'result' =>2],
             '-x+1' => ['x'=>1,'result' =>0],
             '1+-x' => ['x'=>1,'result' =>0],
             '234+234*2323*(3+4)+2^(3+4)' => ['result' => 234 + 234 * 2323 * (3 + 4) + pow(2, 3 + 4)],
             'tan(x)+x+4*x^3+2*x+300' => ['x'=>1.34,'result' => tan(1.34) + 1.34 + 4 * pow(1.34, 3) + 2 * 1.34 + 300],
             '1+-----1' => ['result' => 1 + -(-(-(-(-1))))],
             '1+ - - - - - -1' => ['result' => 1 + - - - - - -1],
             '((((((((((((((((((((1))))))))))))))))))))+1' => ['result' => 2],
             '1+2+3' => ['result' => 1 + 2 + 3],
             'x++ + ++x' => ['x'=>4,'result' => 10],
             '++x*x++' => ['x'=>4,'result' => 25],/**/
             '"Hello world!"+" Yess"' => ['x'=>4,'result' => "Hello world! Yess"],
             /**/
         ] as $k => $v) {
    try {
        $result = $r->ev($k);
        $result0 = null;
        if (isset($v['x'])) $x = $v['x'];
        if (!empty($result)) {
            echo ' [' . json_encode($result) . ']';
            $callback = create_function('$x', 'return ' . $result . ';');
            $result0 = $callback($x);
        }
    } catch (Exception $e) {
        $result = $e->getMessage();
    }
    echo "\n" . $k . '=' . json_encode($result0) ;
    $error = $r->error();
    if (!empty($error)) echo "\n" . 'error: =' . json_encode($error);
    echo "\n";
}
