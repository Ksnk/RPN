<?php
/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 15:35
 */
require '../test/autoloader.php';

// just a simple number calculator
$r = new rpn_class();

set_error_handler(function ($c, $m /*, $f, $l*/) {
    throw new Exception($m, $c);
}, E_ALL & ~E_NOTICE);

$r->option(array(
    'flags'=>0/**/+rpn_class::SHOW_DEBUG+rpn_class::SHOW_ERROR
        +rpn_class::ALLOW_REAL
,
    'operation' => ['+' => 4, '-' => 4, '*' => 5, '/' => 5, '^' => 7,],
    'suffix' => ['%' => 1],
    'unop' => ['-' => 1, '+' => 1],
    'reserved_words' => ['pi' => 0, 'e' => 0,
        'absolute' => 1,
        'arccos' => 1,
        'arccosh' => 1,
        'arcsin' => 1,
        'arcsinh' => 1,
        'arctan' => 1,
        'arctanh' => 1,
        'exp' => 1,
        'floor' => 1,
        'log' => 1,
        'ln' => 1,
        'sign' => 1,
        'sin' => 1,
        'cos' => 1,
        'sinh' => 1,
        'cosh' => 1,
        'sqrt' => 1,
        'sqr' => 1,
        'tan' => 1,
        'tanh' => 1,
        'cbrt' => 1,
        'pow' => 2,
        'summ' => -1,
    ],

    'evaluateTag' => function ($op) use ($r) {
            /** @var operand|null $op */
            $r->log('eval:' . (is_numeric($op) && is_nan($op) ? 'NaN' : json_encode($op)));
            if (!is_a($op,'operand'))
                $result = $op;
            else {
                $result = 0 + (double)$op->val; // явное преобразование к числу
            }
            return $result;
        },
    'executeOp' => function ($op, $_1, $_2, $evaluate) use ($r) {
            $r->log('oper:' . json_encode($_1) . ' ' . $op->val . ' ' . json_encode($_2));

            // проверка на процент от прошлого значения
            if (!$op->unop && is_a($_2,'operand') && $_2->percent) {
                $_2->val = (($_1 = call_user_func($evaluate, $_1)) / 100) * $_2->val;
                $_2->percent=false; // на всякий случай
            }

            if ($op == '+') {
                return call_user_func($evaluate, $_1) + call_user_func($evaluate, $_2);
            } else if ($op == '^') {
                return pow(call_user_func($evaluate, $_1), call_user_func($evaluate, $_2));
            } else if ($op == '-' && $op->unop) {
                return -call_user_func($evaluate, $_2);
            } else if ($op == '-') {
                return call_user_func($evaluate, $_1) - call_user_func($evaluate, $_2);
            } else if ($op == '*') {
                return call_user_func($evaluate, $_1) * call_user_func($evaluate, $_2);
            } else if ($op == '/') {
                return call_user_func($evaluate, $_1) / call_user_func($evaluate, $_2);
            } else if ($op == '%' && $op->unop) {
                $_2->val=call_user_func($evaluate, $_2);$_2->percent=1;
                return $_2;
            } elseif ($op == 'pi') {
                return  pi();
            } elseif ($op == 'e') {
                return  M_E;
            } elseif ($op == 'absolute') {
                return abs(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'arccos') {
                return acos(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'arccosh') {
                return acosh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'arcsin') {
                return asin(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'arcsinh') {
                return asinh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'arctan') {
                return atan(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'arctanh') {
                return atanh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'exp') {
                return exp(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'pow') {
                return pow(call_user_func($evaluate, $_2[0]),call_user_func($evaluate, $_2[1]));
            } elseif ($op == 'floor') {
                return floor(call_user_func($evaluate, $_2[0]));
             } elseif ($op == 'log' || $op == 'ln') {
                return log(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'sign') {
                $number = call_user_func($evaluate, $_2[0]);
                return $number ? abs($number) / $number : 0;
            } elseif ($op == 'sin') {
                return sin(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'cos') {
                return cos(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'sinh') {
                return sinh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'cosh') {
                return cosh(call_user_func($evaluate, $_2[0]));
            } else if ($op == 'sqrt') {
                return sqrt(call_user_func($evaluate, $_2[0]));
            } else if ($op == 'sqr') {
                return pow(call_user_func($evaluate, $_2[0]), 2);
            } elseif ($op == 'tan') {
                return tan(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'tanh') {
                return tanh(call_user_func($evaluate, $_2[0]));
            } elseif ($op == 'cbrt') {
                return pow(call_user_func($evaluate, $_2[0]), 1 / 3);
            } elseif ($op == 'summ') {
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
//*

             '10-20%',
             '-sqrt(sqr(2)+12)',
             '-1',
             '+1+1-1',
             'sin(0)',
             'pi', 'e',
             'sin(pi/2)',
             '3^2+1',
             'sqrt(-1)',
             'sin(pi/2)',
             '1/0',
             '0x8000',
             '3.4E+2',
             '00.3.4',
             '00.334564',
             'pow(2,3)',
             'pow(2,3,4)',
             'pow(2)',
             'summ(1,2,4,5,6,7,,8,4.5,6)', //43.5,
 /**/            'summ(1,2,4,5,6*4^5,7,8,4.5,6)', //43.5,
         ] as $k => $v) {
  //  echo "\n" . json_encode($r->ev($v, false));
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