<?php
/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 15:35
 */
require '../test/autoloader.php';

// just a simple number calculator
$r = new revpolnot_class();

set_error_handler(function ($c, $m /*, $f, $l*/) {
    throw new Exception($m, $c);
}, E_ALL & ~E_NOTICE);

$r->option(array(
  //  'flags'=>0,
    'operation' => ['+' => 4, '-' => 4, '*' => 5, '/' => 5, '^' => 7,],
    'suffix' => ['%' => 1],
    'unop' => ['-' => 1, '+' => 1],
    'tagreg' => '\b(?:\d\w*(?:\.[\d]+)?(?:E[\+\-][\d]+)?)',
    'reserved_words' => ['PI' => 0, 'E' => 0,
        'ABSOLUTE' => 1,
        'ARCCOS' => 1,
        'ARCCOSH' => 1,
        'ARCSIN' => 1,
        'ARCSINH' => 1,
        'ARCTAN' => 1,
        'ARCTANH' => 1,
        'EXP' => 1,
        'FLOOR' => 1,
        'LOG' => 1,
        'LN' => 1,
        'SIGN' => 1,
        'SIN' => 1,
        'COS' => 1,
        'SINH' => 1,
        'COSH' => 1,
        'SQRT' => 1,
        'SQR' => 1,
        'TAN' => 1,
        'TANH' => 1,
        'CBRT' => 1,
        'POW' => 2,
        'SUMM' => -1,
    ],

    'evaluateTag' => function ($op) use ($r) {
            $r->log('eval:' . (is_numeric($op) && is_nan($op) ? 'NaN' : json_encode($op)));
            if (!is_array($op) || !isset($op['data']))
                $result = $op;
            else {
                $result = 0 + $op['data']; // явное преобразование к числу
            }
            return $result;
        },
    'executeOp' => function ($op, $_1, $_2, $evaluate, $unop = false) use ($r) {
            $r->log('oper:' . json_encode($_1) . ' ' . $op . ' ' . json_encode($_2));

            // проверка на процент от прошлого значения
            if (!$unop && is_array($_2) && isset($_2['percent'])) {
                $_2['data'] = (($_1 = call_user_func($evaluate, $_1)) / 100) * $_2['data'];
            }

            if ($op == '+') {
                return call_user_func($evaluate, $_1) + call_user_func($evaluate, $_2);
            } else if ($op == '^') {
                return pow(call_user_func($evaluate, $_1), call_user_func($evaluate, $_2));
            } else if ($op == '-' && $unop) {
                return -call_user_func($evaluate, $_2);
            } else if ($op == '-') {
                return call_user_func($evaluate, $_1) - call_user_func($evaluate, $_2);
            } else if ($op == '*') {
                return call_user_func($evaluate, $_1) * call_user_func($evaluate, $_2);
            } else if ($op == '/') {
                return call_user_func($evaluate, $_1) / call_user_func($evaluate, $_2);
            } else if ($op == '%' && $unop) {
                return array('data' => call_user_func($evaluate, $_2), 'percent' => '1');
            } elseif ($op == 'PI') {
                return pi();
            } elseif ($op == 'E') {
                return M_E;
            } elseif ($op == 'ABSOLUTE') {
                return abs(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'ARCCOS') {
                return acos(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'ARCCOSH') {
                return acosh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'ARCSIN') {
                return asin(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'ARCSINH') {
                return asinh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'ARCTAN') {
                return atan(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'ARCTANH') {
                return atanh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'EXP') {
                return exp(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'POW') {
                return pow(call_user_func($evaluate, $_2[0]),call_user_func($evaluate, $_2[1]));
            } elseif ($op == 'FLOOR') {
                return floor(call_user_func($evaluate, $_2[0]));
             } elseif ($op == 'LOG' || $op == 'LN') {
                return log(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'SIGN') {
                $number = call_user_func($evaluate, $_2[0]);
                return $number ? abs($number) / $number : 0;
            } elseif ($op == 'SIN') {
                return sin(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'COS') {
                return cos(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'SINH') {
                return sinh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'COSH') {
                return cosh(call_user_func($evaluate, $_2[0]));
            } else if ($op == 'SQRT') {
                return sqrt(call_user_func($evaluate, $_2[0]));
            } else if ($op == 'SQR') {
                return pow(call_user_func($evaluate, $_2[0]), 2);
            } elseif ($op == 'TAN') {
                return tan(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'TANH') {
                return tanh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'CBRT') {
                return pow(call_user_func($evaluate, $_2[0]), 1 / 3);
            } elseif ($op == 'SUMM') {
                $result=0;
                foreach($_2 as $x){
                    $result+=call_user_func($evaluate, $x);
                }
                return $result;
            } else {
                $r->error('unknown operation ' . $op);
            }
            return 0;
        }
));

foreach ([
             //  '(-3*-----4)*4++/5',
             '-1',
             '+1+1-1',
             '-sqrt(sqr(2)+12)',
             'sin(0)',
             'pi', 'e',
             'sin(pi/2)',
             '3^2+1',
             'sqrt(-1)',
             'sin(pi/2)',
             '1/0',
             '10-20%',
             '0x8000',
             '3.4E+2',
             '00.3.4',
             '00.334564',
             'pow(2,3)',
             'pow(2,3,4)',
             'pow(2)',
             'summ(1,2,4,5,6,7,,8,4.5,6)',
         ] as $k => $v) {
    echo "\n" . json_encode($r->ev($v, false));
    try {
        $result = $r->ev($v); //var_dump($result);
        if (is_nan($result))
            $result = 'NaN';
        else if (is_infinite($result))
            $result = 'Infinite';
    } catch (Exception $e) {
        $result = $e->getMessage();
    }
    echo "\n" . $v . '=' . json_encode($result);
    $error = $r->error();
    if (!empty($error)) echo "\n" . 'error: =' . json_encode($error);
    echo "\n";
}
/*
 Действительные числа
вводить в виде 7.5, не 7,5
2*x
- умножение
3/x
- деление
x^3
- возведение в степень
x + 7
- сложение
x - 6
- вычитание
Функция f может состоять из функций (обозначения даны в алфавитном порядке):

absolute(x)
Функция - абсолютное значение x (модуль x или |x|)
arccos(x)
Функция - арккосинус от x
arccosh(x)
Функция - арккосинус гиперболический от x
arcsin(x)
Функция - арксинус от x
arcsinh(x)
Функция - арксинус гиперболический от x
arctan(x)
Функция - арктангенс от x
arctanh(x)
Функция - арктангенс гиперболический от x
e
Функция - e это то, которое примерно равно 2.7
exp(x)
Функция - экспонента от x (тоже самое, что и e^x)
floor(x)
Функция - округление x в меньшую сторону (пример floor(4.5)==4.0)
log(x) or ln(x)
Функция - Натуральный логарифм от x (Чтобы получить log7(x), надо ввести log(x)/log(7) (или, например для log10(x)=log(x)/log(10))
pi
Число - "Пи", которое примерно равно 3.14
sign(x)
Функция - Знак x
sin(x)
Функция - Синус от x
cos(x)
Функция - Косинус от x
sinh(x)
Функция - Синус гиперболический от x
cosh(x)
Функция - Косинус гиперболический от x
sqrt(x)
Функция - квадратный корень из x
sqr(x) или x^2
Функция - Квадрат x
tan(x)
Функция - Тангенс от x
tanh(x)
Функция - Тангенс гиперболический от x
cbrt(x)
Функция - кубический корень из x
 */